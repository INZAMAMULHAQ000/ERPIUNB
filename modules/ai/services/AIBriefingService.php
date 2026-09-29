<?php
/**
 * AI F26 — Briefing Service.
 *
 * Generates personalized daily AI briefings from verified ERP data.
 * Test 16: Role-specific intelligence for Finance, Procurement, Inventory, and Management.
 * Test 18: All figures come from actual ERP data.
 *
 * Covers Section 15: Daily AI Briefing.
 */
class AIBriefingService
{
    /**
     * Generate a daily briefing for a user.
     * Test 16: Role-specific — Finance, Procurement, Inventory, Management.
     * Test 18: All figures from actual ERP data, not AI claims.
     */
    public static function generateBriefing(PDO $db, int $userId, int $companyId): array
    {
        $briefing = [
            'user_id' => $userId,
            'company_id' => $companyId,
            'generated_at' => date('Y-m-d H:i:s'),
            'date' => date('F j, Y'),
            'priorities' => [],
            'recommended_actions' => [],
            'stats' => [],
            'role_data' => [],
            'erp_metrics' => [],
        ];

        // Get active intelligence events grouped by severity
        $events = AIIntelligenceService::getEvents($db, [
            'company_id' => $companyId,
            'user_id' => $userId,
            'statuses' => ['new', 'acknowledged'],
            'min_severity' => 'low',
        ], 1, 50);

        $bySeverity = ['critical' => [], 'high' => [], 'medium' => [], 'low' => []];
        foreach ($events['events'] as $event) {
            $bySeverity[$event['severity']][] = $event;
        }

        // Build priorities from critical and high events
        foreach (['critical', 'high'] as $severity) {
            foreach ($bySeverity[$severity] as $event) {
                $briefing['priorities'][] = [
                    'severity' => $severity,
                    'title' => $event['title'],
                    'module' => $event['source_module'],
                    'event_id' => $event['id'],
                ];
            }
        }

        // Build recommended actions from events with recommendations
        foreach ($events['events'] as $event) {
            if (!empty($event['recommendation'])) {
                $briefing['recommended_actions'][] = [
                    'event_id' => $event['id'],
                    'title' => $event['title'],
                    'recommendation' => $event['recommendation'],
                    'severity' => $event['severity'],
                    'module' => $event['source_module'],
                ];
            }
        }

        // Summary stats
        $briefing['stats'] = [
            'total_active' => $events['total'],
            'critical' => count($bySeverity['critical']),
            'high' => count($bySeverity['high']),
            'medium' => count($bySeverity['medium']),
            'low' => count($bySeverity['low']),
        ];

        // Test 16 & 18: Pull real ERP metrics from actual database tables
        $briefing['erp_metrics'] = self::getRealERPMetrics($db, $companyId);
        $briefing['role_data'] = self::getRoleSpecificData($db, $userId, $companyId);

        return $briefing;
    }

    /**
     * Test 18: Get real ERP metrics from actual database tables.
     * All figures come from verified ERP records, not AI claims.
     */
    private static function getRealERPMetrics(PDO $db, int $companyId): array
    {
        $metrics = [];

        // Finance metrics
        if (self::tableExists($db, 'invoices')) {
            $stmt = $db->prepare("
                SELECT
                    COUNT(*) as total_invoices,
                    COALESCE(SUM(total_amount), 0) as total_amount,
                    COALESCE(SUM(CASE WHEN status IN ('sent', 'partially_paid', 'overdue') THEN balance_amount ELSE 0 END), 0) as outstanding_amount,
                    COALESCE(SUM(CASE WHEN status IN ('sent', 'partially_paid', 'overdue') AND due_date < NOW() THEN balance_amount ELSE 0 END), 0) as overdue_amount,
                    COALESCE(SUM(CASE WHEN status IN ('sent', 'partially_paid', 'overdue') AND due_date < NOW() THEN 1 ELSE 0 END), 0) as overdue_count
                FROM invoices
            ");
            $stmt->execute();
            $metrics['finance'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        // Procurement metrics
        if (self::tableExists($db, 'purchase_orders')) {
            $stmt = $db->prepare("
                SELECT
                    COUNT(*) as total_pos,
                    COALESCE(SUM(grand_total), 0) as total_amount,
                    COALESCE(SUM(CASE WHEN status IN ('approved', 'sent') THEN grand_total ELSE 0 END), 0) as pending_amount,
                    COALESCE(SUM(CASE WHEN status IN ('approved', 'sent') AND expected_delivery_date < NOW() THEN 1 ELSE 0 END), 0) as overdue_count,
                    COALESCE(SUM(CASE WHEN status = 'partially_received' THEN 1 ELSE 0 END), 0) as partially_received
                FROM purchase_orders WHERE company_id = ?
            ");
            $stmt->execute([$companyId]);
            $metrics['procurement'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        // Inventory metrics
        if (self::tableExists($db, 'products') && self::tableExists($db, 'inventory')) {
            $stmt = $db->prepare("
                SELECT
                    COUNT(DISTINCT p.id) as total_products,
                    COALESCE(SUM(CASE WHEN i.quantity_on_hand <= 0 THEN 1 ELSE 0 END), 0) as out_of_stock,
                    COALESCE(SUM(CASE WHEN i.quantity_on_hand <= COALESCE(i.reorder_level, 0) THEN 1 ELSE 0 END), 0) as low_stock,
                    COALESCE(SUM(i.quantity_on_hand), 0) as total_units
                FROM products p
                LEFT JOIN inventory i ON p.id = i.product_id
                WHERE p.company_id = ?
            ");
            $stmt->execute([$companyId]);
            $metrics['inventory'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        // Contracts metrics
        if (self::tableExists($db, 'contracts') || self::tableExists($db, 'vendor_contracts')) {
            $table = self::tableExists($db, 'contracts') ? 'contracts' : 'vendor_contracts';
            $stmt = $db->prepare("
                SELECT
                    COUNT(*) as total_contracts,
                    COALESCE(SUM(CASE WHEN status = 'active' AND end_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END), 0) as expiring_soon,
                    COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END), 0) as active
                FROM {$table} WHERE company_id = ?
            ");
            $stmt->execute([$companyId]);
            $metrics['contracts'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        // Customers / Vendors
        if (self::tableExists($db, 'customers')) {
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM customers");
            $stmt->execute();
            $metrics['customers'] = ['total' => (int) $stmt->fetchColumn()];
        }

        if (self::tableExists($db, 'vendors')) {
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM vendors");
            $stmt->execute();
            $metrics['vendors'] = ['total' => (int) $stmt->fetchColumn()];
        }

        return $metrics;
    }

    /**
     * Test 16: Role-specific data for Finance, Procurement, Inventory, Management users.
     */
    private static function getRoleSpecificData(PDO $db, int $userId, int $companyId): array
    {
        $roleData = [];

        // Determine user's likely role from their permissions/modules they access
        $modules = self::getUserModules($db, $userId);

        // Finance role data
        if (in_array('finance', $modules)) {
            $roleData['finance'] = self::getFinanceBriefingData($db, $companyId);
        }

        // Procurement role data
        if (in_array('procurement', $modules)) {
            $roleData['procurement'] = self::getProcurementBriefingData($db, $companyId);
        }

        // Inventory role data
        if (in_array('inventory', $modules)) {
            $roleData['inventory'] = self::getInventoryBriefingData($db, $companyId);
        }

        // Management sees everything
        if (in_array('management', $modules)) {
            $roleData['management'] = self::getManagementBriefingData($db, $companyId);
        }

        return $roleData;
    }

    /**
     * Get user's accessible modules based on their role/permissions.
     */
    private static function getUserModules(PDO $db, int $userId): array
    {
        $modules = ['management']; // Default: all users see management overview

        try {
            // Check user permissions to determine role
            if (function_exists('hasPermission')) {
                if (hasPermission('finance.view') || hasPermission('invoices.view') || hasPermission('accounts.view')) {
                    $modules[] = 'finance';
                }
                if (hasPermission('procurement.view') || hasPermission('purchase_orders.view')) {
                    $modules[] = 'procurement';
                }
                if (hasPermission('inventory.view') || hasPermission('products.view')) {
                    $modules[] = 'inventory';
                }
            }
        } catch (\Throwable $e) {
            // Default to all modules
            $modules = ['finance', 'procurement', 'inventory', 'management'];
        }

        return $modules;
    }

    /**
     * Finance-specific briefing data from real ERP.
     */
    private static function getFinanceBriefingData(PDO $db, int $companyId): array
    {
        $data = ['title' => 'Finance Overview', 'items' => []];

        if (!self::tableExists($db, 'invoices')) return $data;

        // Top overdue invoices
        $stmt = $db->prepare("
            SELECT i.invoice_number, c.company_name as client_name, i.balance_amount as amount, i.due_date,
                   DATEDIFF(NOW(), i.due_date) as days_overdue
            FROM invoices i
            LEFT JOIN clients c ON c.id = i.client_id
            WHERE i.status IN ('sent', 'partially_paid', 'overdue') AND i.due_date < NOW()
            ORDER BY i.balance_amount DESC LIMIT 5
        ");
        $stmt->execute();
        $data['top_overdue'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Cash flow summary
        $stmt = $db->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN status = 'paid' AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN paid_amount ELSE 0 END), 0) as paid_last_30d,
                COALESCE(SUM(CASE WHEN status IN ('sent', 'partially_paid', 'overdue') THEN balance_amount ELSE 0 END), 0) as outstanding,
                COALESCE(SUM(CASE WHEN status IN ('sent', 'partially_paid', 'overdue') AND due_date < NOW() THEN balance_amount ELSE 0 END), 0) as overdue
            FROM invoices
        ");
        $stmt->execute();
        $data['cash_flow'] = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data;
    }

    /**
     * Procurement-specific briefing data from real ERP.
     */
    private static function getProcurementBriefingData(PDO $db, int $companyId): array
    {
        $data = ['title' => 'Procurement Overview', 'items' => []];

        if (!self::tableExists($db, 'purchase_orders')) return $data;

        // Top overdue POs
        $stmt = $db->prepare("
            SELECT po.po_number, v.vendor_name as supplier_name, po.grand_total as total, po.expected_delivery_date as expected_delivery,
                   DATEDIFF(NOW(), po.expected_delivery_date) as days_overdue
            FROM purchase_orders po
            LEFT JOIN vendors v ON v.id = po.vendor_id
            WHERE po.company_id = ? AND po.status IN ('approved', 'sent') AND po.expected_delivery_date < NOW()
            ORDER BY po.grand_total DESC LIMIT 5
        ");
        $stmt->execute([$companyId]);
        $data['top_overdue_pos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // PO stats
        $stmt = $db->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN status = 'pending_approval' THEN grand_total ELSE 0 END), 0) as pending_approval,
                COALESCE(SUM(CASE WHEN status IN ('approved', 'sent') THEN grand_total ELSE 0 END), 0) as in_progress,
                COALESCE(SUM(CASE WHEN status = 'fully_received' AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN grand_total ELSE 0 END), 0) as received_last_30d
            FROM purchase_orders WHERE company_id = ?
        ");
        $stmt->execute([$companyId]);
        $data['po_stats'] = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data;
    }

    /**
     * Inventory-specific briefing data from real ERP.
     */
    private static function getInventoryBriefingData(PDO $db, int $companyId): array
    {
        $data = ['title' => 'Inventory Overview', 'items' => []];

        if (!self::tableExists($db, 'products') || !self::tableExists($db, 'inventory')) return $data;

        // Low stock items
        $stmt = $db->prepare("
            SELECT p.name, p.sku, i.quantity_on_hand, i.reorder_level
            FROM products p
            JOIN inventory i ON p.id = i.product_id
            WHERE p.company_id = ? AND i.quantity_on_hand <= COALESCE(i.reorder_level, 0)
            ORDER BY (i.quantity_on_hand / GREATEST(COALESCE(i.reorder_level, 1), 1)) ASC
            LIMIT 5
        ");
        $stmt->execute([$companyId]);
        $data['low_stock_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Inventory summary
        $stmt = $db->prepare("
            SELECT
                COUNT(DISTINCT p.id) as total_products,
                COALESCE(SUM(CASE WHEN i.quantity_on_hand = 0 THEN 1 ELSE 0 END), 0) as out_of_stock,
                COALESCE(SUM(CASE WHEN i.quantity_on_hand > 0 AND i.quantity_on_hand <= COALESCE(i.reorder_level, 0) THEN 1 ELSE 0 END), 0) as low_stock,
                COALESCE(SUM(i.quantity_on_hand), 0) as total_units
            FROM products p
            LEFT JOIN inventory i ON p.id = i.product_id
            WHERE p.company_id = ?
        ");
        $stmt->execute([$companyId]);
        $data['inventory_summary'] = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data;
    }

    /**
     * Management briefing data — aggregated view.
     */
    private static function getManagementBriefingData(PDO $db, int $companyId): array
    {
        $data = ['title' => 'Management Overview', 'items' => []];

        // Active intelligence events by module
        $stmt = $db->prepare("
            SELECT source_module, COUNT(*) as count, severity
            FROM ai_intelligence_events
            WHERE company_id = ? AND status IN ('new', 'acknowledged')
            GROUP BY source_module, severity
            ORDER BY FIELD(severity, 'critical', 'high', 'medium', 'low')
        ");
        $stmt->execute([$companyId]);
        $data['events_by_module'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Pending approvals (from agent tasks)
        if (self::tableExists($db, 'ai_agent_approvals')) {
            $stmt = $db->prepare("
                SELECT COUNT(*) as pending_count
                FROM ai_agent_approvals
                WHERE company_id = ? AND status = 'pending'
            ");
            $stmt->execute([$companyId]);
            $data['pending_approvals'] = ['count' => (int) $stmt->fetchColumn()];
        }

        return $data;
    }

    /**
     * Format briefing as human-readable text.
     */
    public static function formatBriefing(array $briefing): string
    {
        $lines = ["# AI Daily Briefing — " . $briefing['date'] ?? date('F j, Y')];

        $lines[] = "";
        $lines[] = "## Today's Priorities";

        if (empty($briefing['priorities'])) {
            $lines[] = "- No critical or high-priority issues.";
        } else {
            foreach ($briefing['priorities'] as $p) {
                $lines[] = "- [" . strtoupper($p['severity']) . "] " . $p['title'];
            }
        }

        $lines[] = "";
        $lines[] = "## ERP Summary";

        // Finance
        if (!empty($briefing['erp_metrics']['finance'])) {
            $f = $briefing['erp_metrics']['finance'];
            $lines[] = "### Finance";
            $lines[] = "- Total invoices: {$f['total_invoices']}";
            $lines[] = "- Outstanding: $" . number_format($f['outstanding_amount'], 2);
            $lines[] = "- Overdue: " . $f['overdue_count'] . " invoices ($" . number_format($f['overdue_amount'], 2) . ")";
        }

        // Procurement
        if (!empty($briefing['erp_metrics']['procurement'])) {
            $p = $briefing['erp_metrics']['procurement'];
            $lines[] = "### Procurement";
            $lines[] = "- Total POs: {$p['total_pos']}";
            $lines[] = "- Pending: $" . number_format($p['pending_amount'], 2);
            $lines[] = "- Overdue deliveries: {$p['overdue_count']}";
        }

        // Inventory
        if (!empty($briefing['erp_metrics']['inventory'])) {
            $i = $briefing['erp_metrics']['inventory'];
            $lines[] = "### Inventory";
            $lines[] = "- Total products: {$i['total_products']}";
            $lines[] = "- Out of stock: {$i['out_of_stock']}";
            $lines[] = "- Low stock: {$i['low_stock']}";
            $lines[] = "- Total units: " . number_format($i['total_units']);
        }

        // Contracts
        if (!empty($briefing['erp_metrics']['contracts'])) {
            $c = $briefing['erp_metrics']['contracts'];
            $lines[] = "### Contracts";
            $lines[] = "- Active: {$c['active']}";
            $lines[] = "- Expiring within 30 days: {$c['expiring_soon']}";
        }

        $lines[] = "";
        $lines[] = "## Recommended Actions";

        if (empty($briefing['recommended_actions'])) {
            $lines[] = "- No recommended actions at this time.";
        } else {
            $i = 1;
            foreach (array_slice($briefing['recommended_actions'], 0, 5) as $action) {
                $lines[] = "{$i}. [{$action['severity']}] {$action['recommendation']}";
                $i++;
            }
        }

        $lines[] = "";
        $lines[] = "## Summary";
        $lines[] = "- Active issues: {$briefing['stats']['total_active']}";
        $lines[] = "- Critical: {$briefing['stats']['critical']}";
        $lines[] = "- High: {$briefing['stats']['high']}";

        return implode("\n", $lines);
    }

    /**
     * Check if a table exists.
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
