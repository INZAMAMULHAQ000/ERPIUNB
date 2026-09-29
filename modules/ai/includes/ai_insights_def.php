<?php
/**
 * AI F14 — Initial Insight Definitions.
 *
 * Procurement insights based on genuinely available ERP data.
 */

// ═══════════════════════════════════════════════════════
// A. Overdue Purchase Requisitions
// ═══════════════════════════════════════════════════════
AIInsightRegistry::register([
    'id' => 'procurement.overdue_requisitions',
    'name' => 'Overdue Purchase Requisitions',
    'description' => 'Detects purchase requisitions that have remained pending beyond a configurable period.',
    'module' => 'procurement',
    'category' => 'procurement',
    'type' => 'hybrid',
    'enabled' => true,
    'required_permissions' => ['procurement.view'],
    'evaluation' => [
        'tool' => 'procurement.search_purchase_requisitions',
        'params' => ['status' => 'pending'],
        'conditions' => [
            ['field' => 'age_days', 'date_field' => 'created_at', 'operator' => 'gte', 'threshold_days' => 7],
        ],
    ],
    'severity_rules' => [
        ['field' => 'age_days', 'thresholds' => [
            ['min' => 1, 'max' => 5, 'severity' => 'notice'],
            ['min' => 6, 'max' => 10, 'severity' => 'warning'],
            ['min' => 11, 'severity' => 'critical'],
        ]],
    ],
    'target_users' => ['resolver' => 'module_managers'],
    'navigation' => [
        'route' => '/modules/procurement/requisitions/index.php',
        'label' => 'View Requisitions',
    ],
    'dedup_strategy' => 'same_hash',
    'evaluation_frequency' => 'hourly',
    'max_records' => 100,
]);

// ═══════════════════════════════════════════════════════
// B. High Pending Requisition Volume
// ═══════════════════════════════════════════════════════
AIInsightRegistry::register([
    'id' => 'procurement.high_pending_volume',
    'name' => 'High Pending Requisition Volume',
    'description' => 'Alerts when the number of pending purchase requisitions exceeds a threshold.',
    'module' => 'procurement',
    'category' => 'procurement',
    'type' => 'rule_based',
    'enabled' => true,
    'required_permissions' => ['procurement.view'],
    'evaluation' => [
        'tool' => 'procurement.search_purchase_requisitions',
        'params' => ['status' => 'pending'],
        'conditions' => [
            ['field' => 'count', 'operator' => 'gte', 'threshold' => 10],
        ],
    ],
    'severity_rules' => [
        ['field' => 'count', 'thresholds' => [
            ['min' => 10, 'max' => 19, 'severity' => 'notice'],
            ['min' => 20, 'max' => 49, 'severity' => 'warning'],
            ['min' => 50, 'severity' => 'critical'],
        ]],
    ],
    'target_users' => ['resolver' => 'module_managers'],
    'navigation' => [
        'route' => '/modules/procurement/requisitions/index.php',
        'label' => 'View Pending Requisitions',
    ],
    'dedup_strategy' => 'same_day',
    'evaluation_frequency' => 'hourly',
    'max_records' => 200,
]);

// ═══════════════════════════════════════════════════════
// C. Purchase Order Delays
// ═══════════════════════════════════════════════════════
AIInsightRegistry::register([
    'id' => 'procurement.po_delays',
    'name' => 'Purchase Order Delays',
    'description' => 'Detects purchase orders where expected delivery dates have passed.',
    'module' => 'procurement',
    'category' => 'procurement',
    'type' => 'rule_based',
    'enabled' => true,
    'required_permissions' => ['procurement.view'],
    'evaluation' => [
        'tool' => 'procurement.search_purchase_orders',
        'params' => ['status' => 'open'],
        'conditions' => [
            ['field' => 'age_days', 'date_field' => 'expected_delivery_date', 'operator' => 'gte', 'threshold_days' => 0],
        ],
    ],
    'severity_rules' => [
        ['field' => 'age_days', 'thresholds' => [
            ['min' => 1, 'max' => 3, 'severity' => 'info'],
            ['min' => 4, 'max' => 7, 'severity' => 'notice'],
            ['min' => 8, 'max' => 14, 'severity' => 'warning'],
            ['min' => 15, 'severity' => 'critical'],
        ]],
    ],
    'target_users' => ['resolver' => 'module_managers'],
    'navigation' => [
        'route' => '/modules/procurement/purchase_orders/index.php',
        'label' => 'View Purchase Orders',
    ],
    'dedup_strategy' => 'same_hash',
    'evaluation_frequency' => 'daily',
    'max_records' => 100,
]);

// ═══════════════════════════════════════════════════════
// D. Supplier Concentration
// ═══════════════════════════════════════════════════════
AIInsightRegistry::register([
    'id' => 'procurement.supplier_concentration',
    'name' => 'Supplier Concentration',
    'description' => 'Analytical insight showing when a high percentage of purchase orders involve a single supplier.',
    'module' => 'procurement',
    'category' => 'procurement',
    'type' => 'hybrid',
    'enabled' => true,
    'required_permissions' => ['procurement.view'],
    'evaluation' => [
        'tool' => 'procurement.search_purchase_orders',
        'params' => ['status' => 'open'],
        'conditions' => [
            ['field' => 'percentage', 'group_field' => 'vendor_id', 'operator' => 'gte', 'threshold' => 50],
        ],
    ],
    'severity_rules' => [
        ['field' => 'percentage', 'thresholds' => [
            ['min' => 50, 'max' => 64, 'severity' => 'info'],
            ['min' => 65, 'max' => 79, 'severity' => 'notice'],
            ['min' => 80, 'severity' => 'warning'],
        ]],
    ],
    'target_users' => ['resolver' => 'module_managers'],
    'navigation' => [
        'route' => '/modules/procurement/purchase_orders/index.php',
        'label' => 'View Purchase Orders',
    ],
    'dedup_strategy' => 'same_day',
    'evaluation_frequency' => 'daily',
    'max_records' => 200,
]);
