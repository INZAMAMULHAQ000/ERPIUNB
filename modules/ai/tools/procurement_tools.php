<?php
/**
 * AI F8 — Procurement Read-Only Tools.
 *
 * Registers and implements read-only tools for Procurement data access.
 */

// ═══ Tool Definitions ═══

AIToolRegistry::register([
    'id'                  => 'procurement.search_vendors',
    'name'                => 'search_vendors',
    'description'         => 'Search vendors/suppliers by name or code. Returns vendor ID, name, code, status, and contact info.',
    'module'              => 'procurement',
    'risk_level'          => AI_TOOL_RISK_READ_ONLY,
    'required_permissions'=> ['procurement.view'],
    'input_schema'        => [
        'type'       => 'object',
        'properties' => [
            'query'  => ['type' => 'string', 'description' => 'Search term to match against vendor name or code'],
            'status' => ['type' => 'string', 'enum' => ['active', 'inactive'], 'description' => 'Filter by status'],
            'limit'  => ['type' => 'integer', 'description' => 'Max results (default 10, max 20)'],
        ],
        'required' => ['query'],
    ],
    'handler_class'       => 'AIToolSearchVendors',
]);

AIToolRegistry::register([
    'id'                  => 'procurement.get_vendor',
    'name'                => 'get_vendor',
    'description'         => 'Get detailed information about a specific vendor by ID.',
    'module'              => 'procurement',
    'risk_level'          => AI_TOOL_RISK_READ_ONLY,
    'required_permissions'=> ['procurement.view'],
    'input_schema'        => [
        'type'       => 'object',
        'properties' => [
            'vendor_id' => ['type' => 'integer', 'description' => 'The vendor ID'],
        ],
        'required' => ['vendor_id'],
    ],
    'handler_class'       => 'AIToolGetVendor',
]);

AIToolRegistry::register([
    'id'                  => 'procurement.search_purchase_requisitions',
    'name'                => 'search_purchase_requisitions',
    'description'         => 'Search purchase requisitions by PR number, status, or requester. Returns PR summaries.',
    'module'              => 'procurement',
    'risk_level'          => AI_TOOL_RISK_READ_ONLY,
    'required_permissions'=> ['procurement.view'],
    'input_schema'        => [
        'type'       => 'object',
        'properties' => [
            'query'   => ['type' => 'string', 'description' => 'Search term to match against PR number or purpose'],
            'status'  => ['type' => 'string', 'enum' => ['draft', 'submitted', 'pending_approval', 'approved', 'rejected', 'cancelled', 'closed'], 'description' => 'Filter by status'],
            'limit'   => ['type' => 'integer', 'description' => 'Max results (default 10, max 20)'],
        ],
        'required' => [],
    ],
    'handler_class'       => 'AIToolSearchPurchaseRequisitions',
]);

AIToolRegistry::register([
    'id'                  => 'procurement.get_purchase_requisition',
    'name'                => 'get_purchase_requisition',
    'description'         => 'Get detailed information about a specific purchase requisition by ID.',
    'module'              => 'procurement',
    'risk_level'          => AI_TOOL_RISK_READ_ONLY,
    'required_permissions'=> ['procurement.view'],
    'input_schema'        => [
        'type'       => 'object',
        'properties' => [
            'requisition_id' => ['type' => 'integer', 'description' => 'The purchase requisition ID'],
        ],
        'required' => ['requisition_id'],
    ],
    'handler_class'       => 'AIToolGetPurchaseRequisition',
]);

AIToolRegistry::register([
    'id'                  => 'procurement.search_purchase_orders',
    'name'                => 'search_purchase_orders',
    'description'         => 'Search purchase orders by PO number, vendor, or status. Returns PO summaries with amounts.',
    'module'              => 'procurement',
    'risk_level'          => AI_TOOL_RISK_READ_ONLY,
    'required_permissions'=> ['procurement.view'],
    'input_schema'        => [
        'type'       => 'object',
        'properties' => [
            'query'     => ['type' => 'string', 'description' => 'Search term to match against PO number'],
            'vendor_id' => ['type' => 'integer', 'description' => 'Filter by vendor ID'],
            'status'    => ['type' => 'string', 'enum' => ['draft', 'pending_approval', 'approved', 'rejected', 'sent', 'acknowledged', 'partially_received', 'fully_received', 'partially_billed', 'fully_billed', 'closed', 'cancelled'], 'description' => 'Filter by status'],
            'limit'     => ['type' => 'integer', 'description' => 'Max results (default 10, max 20)'],
        ],
        'required' => [],
    ],
    'handler_class'       => 'AIToolSearchPurchaseOrders',
]);

AIToolRegistry::register([
    'id'                  => 'procurement.get_purchase_order',
    'name'                => 'get_purchase_order',
    'description'         => 'Get detailed information about a specific purchase order by ID.',
    'module'              => 'procurement',
    'risk_level'          => AI_TOOL_RISK_READ_ONLY,
    'required_permissions'=> ['procurement.view'],
    'input_schema'        => [
        'type'       => 'object',
        'properties' => [
            'order_id' => ['type' => 'integer', 'description' => 'The purchase order ID'],
        ],
        'required' => ['order_id'],
    ],
    'handler_class'       => 'AIToolGetPurchaseOrder',
]);


// ═══ Tool Implementations ═══

/**
 * Search vendors by name or code.
 */
class AIToolSearchVendors extends AIToolHandlerBase
{
    public function execute(array $params): array
    {
        $query = $params['query'] ?? '';
        $status = $params['status'] ?? null;
        $limit = min((int) ($params['limit'] ?? 10), AIToolExecutor::MAX_RECORDS_RETURNED);

        $sql = "SELECT id, vendor_code, vendor_name, contact_person, email, phone, status
                FROM vendors WHERE 1=1";
        $args = [];

        if ($query !== '') {
            $sql .= " AND (vendor_name LIKE ? OR vendor_code LIKE ?)";
            $searchTerm = $this->sanitizeSearchTerm($query);
            $args[] = $searchTerm;
            $args[] = $searchTerm;
        }

        if ($status) {
            $sql .= " AND status = ?";
            $args[] = $status;
        }

        $sql .= " ORDER BY vendor_name ASC LIMIT ?";
        $args[] = $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($args);
        $records = $stmt->fetchAll();

        // Get total count
        $countSql = "SELECT COUNT(*) FROM vendors WHERE 1=1";
        $countArgs = [];
        if ($query !== '') {
            $countSql .= " AND (vendor_name LIKE ? OR vendor_code LIKE ?)";
            $searchTerm = $this->sanitizeSearchTerm($query);
            $countArgs[] = $searchTerm;
            $countArgs[] = $searchTerm;
        }
        if ($status) {
            $countSql .= " AND status = ?";
            $countArgs[] = $status;
        }
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($countArgs);
        $total = (int) $countStmt->fetchColumn();

        // Normalize
        $normalized = [];
        foreach ($records as $r) {
            $normalized[] = $this->normalizeRecord($r, [
                'id'             => 'ID',
                'vendor_code'    => 'Code',
                'vendor_name'    => 'Name',
                'contact_person' => 'Contact',
                'email'          => 'Email',
                'phone'          => 'Phone',
                'status'         => 'Status',
            ]);
        }

        return [
            'success' => true,
            'data'    => $this->formatResults($normalized, 'vendors', $total),
        ];
    }
}

/**
 * Get a single vendor by ID.
 */
class AIToolGetVendor extends AIToolHandlerBase
{
    public function execute(array $params): array
    {
        $vendorId = (int) ($params['vendor_id'] ?? 0);
        if ($vendorId <= 0) {
            return ['success' => false, 'error' => 'Invalid vendor ID.'];
        }

        $stmt = $this->db->prepare("
            SELECT id, vendor_code, vendor_name, contact_person, email, phone,
                   address, website, payment_terms, currency, vat_number, status
            FROM vendors WHERE id = ?
        ");
        $stmt->execute([$vendorId]);
        $vendor = $stmt->fetch();

        if (!$vendor) {
            return ['success' => false, 'error' => 'Vendor not found or not accessible.'];
        }

        $normalized = $this->normalizeRecord($vendor, [
            'id'             => 'ID',
            'vendor_code'    => 'Code',
            'vendor_name'    => 'Name',
            'contact_person' => 'Contact Person',
            'email'          => 'Email',
            'phone'          => 'Phone',
            'address'        => 'Address',
            'website'        => 'Website',
            'payment_terms'  => 'Payment Terms',
            'currency'       => 'Currency',
            'vat_number'     => 'VAT Number',
            'status'         => 'Status',
        ]);

        // Format as readable string
        $lines = ["Vendor: {$vendor['vendor_name']}"];
        foreach ($normalized as $key => $value) {
            if ($value !== null && $value !== '' && $key !== 'Name') {
                $lines[] = "{$key}: {$value}";
            }
        }

        return ['success' => true, 'data' => implode("\n", $lines)];
    }
}

/**
 * Search purchase requisitions.
 */
class AIToolSearchPurchaseRequisitions extends AIToolHandlerBase
{
    public function execute(array $params): array
    {
        $query = $params['query'] ?? '';
        $status = $params['status'] ?? null;
        $limit = min((int) ($params['limit'] ?? 10), AIToolExecutor::MAX_RECORDS_RETURNED);

        $sql = "SELECT pr.id, pr.pr_number, pr.request_date, pr.required_date,
                       pr.priority, pr.purpose, pr.status, pr.grand_total, pr.currency,
                       u.name as requester_name
                FROM purchase_requisitions pr
                LEFT JOIN users u ON pr.requester_id = u.id
                WHERE 1=1";
        $args = [];

        if ($query !== '') {
            $sql .= " AND (pr.pr_number LIKE ? OR pr.purpose LIKE ?)";
            $searchTerm = $this->sanitizeSearchTerm($query);
            $args[] = $searchTerm;
            $args[] = $searchTerm;
        }

        if ($status) {
            $sql .= " AND pr.status = ?";
            $args[] = $status;
        }

        $sql .= " ORDER BY pr.created_at DESC LIMIT ?";
        $args[] = $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($args);
        $records = $stmt->fetchAll();

        // Count
        $countSql = "SELECT COUNT(*) FROM purchase_requisitions pr WHERE 1=1";
        $countArgs = [];
        if ($query !== '') {
            $countSql .= " AND (pr.pr_number LIKE ? OR pr.purpose LIKE ?)";
            $searchTerm = $this->sanitizeSearchTerm($query);
            $countArgs[] = $searchTerm;
            $countArgs[] = $searchTerm;
        }
        if ($status) {
            $countSql .= " AND pr.status = ?";
            $countArgs[] = $status;
        }
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($countArgs);
        $total = (int) $countStmt->fetchColumn();

        $normalized = [];
        foreach ($records as $r) {
            $normalized[] = $this->normalizeRecord($r, [
                'pr_number'      => 'PR Number',
                'requester_name' => 'Requester',
                'request_date'   => 'Request Date',
                'required_date'  => 'Required Date',
                'priority'       => 'Priority',
                'purpose'        => 'Purpose',
                'status'         => 'Status',
                'grand_total'    => 'Total',
                'currency'       => 'Currency',
            ]);
        }

        return [
            'success' => true,
            'data'    => $this->formatResults($normalized, 'purchase requisitions', $total),
        ];
    }
}

/**
 * Get a single purchase requisition by ID.
 */
class AIToolGetPurchaseRequisition extends AIToolHandlerBase
{
    public function execute(array $params): array
    {
        $prId = (int) ($params['requisition_id'] ?? 0);
        if ($prId <= 0) {
            return ['success' => false, 'error' => 'Invalid requisition ID.'];
        }

        $stmt = $this->db->prepare("
            SELECT pr.id, pr.pr_number, pr.request_date, pr.required_date,
                   pr.priority, pr.purpose, pr.description, pr.status,
                   pr.subtotal, pr.tax_total, pr.discount_total, pr.grand_total, pr.currency,
                   u.name as requester_name, pr.created_at
            FROM purchase_requisitions pr
            LEFT JOIN users u ON pr.requester_id = u.id
            WHERE pr.id = ?
        ");
        $stmt->execute([$prId]);
        $pr = $stmt->fetch();

        if (!$pr) {
            return ['success' => false, 'error' => 'Purchase requisition not found or not accessible.'];
        }

        $normalized = $this->normalizeRecord($pr, [
            'pr_number'      => 'PR Number',
            'requester_name' => 'Requester',
            'request_date'   => 'Request Date',
            'required_date'  => 'Required Date',
            'priority'       => 'Priority',
            'purpose'        => 'Purpose',
            'description'    => 'Description',
            'status'         => 'Status',
            'subtotal'       => 'Subtotal',
            'tax_total'      => 'Tax',
            'discount_total' => 'Discount',
            'grand_total'    => 'Grand Total',
            'currency'       => 'Currency',
            'created_at'     => 'Created',
        ]);

        $lines = ["Purchase Requisition: {$pr['pr_number']}"];
        foreach ($normalized as $key => $value) {
            if ($value !== null && $value !== '' && $key !== 'PR Number') {
                $lines[] = "{$key}: {$value}";
            }
        }

        return ['success' => true, 'data' => implode("\n", $lines)];
    }
}

/**
 * Search purchase orders.
 */
class AIToolSearchPurchaseOrders extends AIToolHandlerBase
{
    public function execute(array $params): array
    {
        $query = $params['query'] ?? '';
        $vendorId = (int) ($params['vendor_id'] ?? 0);
        $status = $params['status'] ?? null;
        $limit = min((int) ($params['limit'] ?? 10), AIToolExecutor::MAX_RECORDS_RETURNED);

        $sql = "SELECT po.id, po.po_number, po.po_date, po.status, po.currency,
                       po.subtotal, po.tax_total, po.discount_total, po.grand_total,
                       v.vendor_name, u.name as buyer_name
                FROM purchase_orders po
                LEFT JOIN vendors v ON po.vendor_id = v.id
                LEFT JOIN users u ON po.buyer_id = u.id
                WHERE 1=1";
        $args = [];

        if ($query !== '') {
            $sql .= " AND po.po_number LIKE ?";
            $args[] = $this->sanitizeSearchTerm($query);
        }

        if ($vendorId > 0) {
            $sql .= " AND po.vendor_id = ?";
            $args[] = $vendorId;
        }

        if ($status) {
            $sql .= " AND po.status = ?";
            $args[] = $status;
        }

        $sql .= " ORDER BY po.created_at DESC LIMIT ?";
        $args[] = $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($args);
        $records = $stmt->fetchAll();

        // Count
        $countSql = "SELECT COUNT(*) FROM purchase_orders po WHERE 1=1";
        $countArgs = [];
        if ($query !== '') {
            $countSql .= " AND po.po_number LIKE ?";
            $countArgs[] = $this->sanitizeSearchTerm($query);
        }
        if ($vendorId > 0) {
            $countSql .= " AND po.vendor_id = ?";
            $countArgs[] = $vendorId;
        }
        if ($status) {
            $countSql .= " AND po.status = ?";
            $countArgs[] = $status;
        }
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($countArgs);
        $total = (int) $countStmt->fetchColumn();

        $normalized = [];
        foreach ($records as $r) {
            $normalized[] = $this->normalizeRecord($r, [
                'po_number'    => 'PO Number',
                'vendor_name'  => 'Vendor',
                'po_date'      => 'PO Date',
                'status'       => 'Status',
                'grand_total'  => 'Total',
                'currency'     => 'Currency',
                'buyer_name'   => 'Buyer',
            ]);
        }

        return [
            'success' => true,
            'data'    => $this->formatResults($normalized, 'purchase orders', $total),
        ];
    }
}

/**
 * Get a single purchase order by ID.
 */
class AIToolGetPurchaseOrder extends AIToolHandlerBase
{
    public function execute(array $params): array
    {
        $poId = (int) ($params['order_id'] ?? 0);
        if ($poId <= 0) {
            return ['success' => false, 'error' => 'Invalid order ID.'];
        }

        $stmt = $this->db->prepare("
            SELECT po.id, po.po_number, po.po_date, po.expected_delivery_date,
                   po.status, po.currency, po.payment_terms, po.delivery_terms,
                   po.subtotal, po.tax_total, po.discount_total, po.other_charges_total,
                   po.grand_total, po.special_instructions,
                   v.vendor_name, v.vendor_code,
                   u.name as buyer_name, req.name as requester_name,
                   po.created_at
            FROM purchase_orders po
            LEFT JOIN vendors v ON po.vendor_id = v.id
            LEFT JOIN users u ON po.buyer_id = u.id
            LEFT JOIN users req ON po.requester_id = req.id
            WHERE po.id = ?
        ");
        $stmt->execute([$poId]);
        $po = $stmt->fetch();

        if (!$po) {
            return ['success' => false, 'error' => 'Purchase order not found or not accessible.'];
        }

        $normalized = $this->normalizeRecord($po, [
            'po_number'              => 'PO Number',
            'vendor_name'            => 'Vendor',
            'vendor_code'            => 'Vendor Code',
            'po_date'                => 'PO Date',
            'expected_delivery_date' => 'Expected Delivery',
            'status'                 => 'Status',
            'buyer_name'             => 'Buyer',
            'requester_name'         => 'Requester',
            'payment_terms'          => 'Payment Terms',
            'delivery_terms'         => 'Delivery Terms',
            'subtotal'               => 'Subtotal',
            'tax_total'              => 'Tax',
            'discount_total'         => 'Discount',
            'other_charges_total'    => 'Other Charges',
            'grand_total'            => 'Grand Total',
            'currency'               => 'Currency',
            'special_instructions'   => 'Special Instructions',
            'created_at'             => 'Created',
        ]);

        $lines = ["Purchase Order: {$po['po_number']}"];
        foreach ($normalized as $key => $value) {
            if ($value !== null && $value !== '' && $key !== 'PO Number') {
                $lines[] = "{$key}: {$value}";
            }
        }

        // Also get line items
        $itemStmt = $this->db->prepare("
            SELECT description, quantity, unit_price, tax_rate, line_total
            FROM purchase_order_items
            WHERE po_id = ?
            ORDER BY id ASC
            LIMIT 20
        ");
        $itemStmt->execute([$poId]);
        $items = $itemStmt->fetchAll();

        if (!empty($items)) {
            $lines[] = '';
            $lines[] = 'Line Items:';
            foreach ($items as $i => $item) {
                $lines[] = '  ' . ($i + 1) . '. ' . ($item['description'] ?? 'Item')
                    . ' | Qty: ' . ($item['quantity'] ?? 0)
                    . ' | Unit Price: ' . ($item['unit_price'] ?? 0)
                    . ' | Total: ' . ($item['line_total'] ?? 0);
            }
        }

        return ['success' => true, 'data' => implode("\n", $lines)];
    }
}
