<?php
/**
 * Generic KPI Widget Handler
 *
 * Handles simple stat-card widgets that just need one SQL count/sum query.
 * Maps widget_key to a query configuration.
 *
 * When a specific handler file doesn't exist, the API falls back to this.
 */

/**
 * Main entry point for generic KPI handling
 */
function widget_generic_kpi($widgetKey, $definition, $params) {
    $handler = getKpiHandler($widgetKey);
    if (!$handler) {
        return false; // Let the API return "not implemented"
    }

    $db = getDB();
    $data = [];

    if (isset($handler['query'])) {
        $stmt = $db->query($handler['query']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $data = $result ? $result : [];

        // Map value to standard format
        if (isset($data[$handler['value_column'] ?? 'value'])) {
            $value = $data[$handler['value_column'] ?? 'value'];
        } else {
            $value = reset($data);
        }

        $data = [
            'value' => $value,
            'label' => $handler['label'] ?? $definition['name'],
            'icon' => $handler['icon'] ?? $definition['icon'],
            'color' => $handler['color'] ?? 'blue',
            'subtitle' => $handler['subtitle'] ?? '',
            'link' => $handler['link'] ?? '',
            'trend' => $handler['trend'] ?? null,
        ];

        // Compute trend if trend_query exists
        if (isset($handler['trend_query'])) {
            $trendStmt = $db->query($handler['trend_query']);
            $trendResult = $trendStmt->fetch(PDO::FETCH_ASSOC);
            if ($trendResult) {
                $data['trend'] = $trendResult['trend'] ?? null;
                $data['trend_label'] = $trendResult['trend_label'] ?? '';
                $data['trend_direction'] = ($data['trend'] ?? 0) >= 0 ? 'up' : 'down';
            }
        }
    }

    return [
        'success' => true,
        'widget_key' => $widgetKey,
        'render_type' => 'server_html',
        'data' => $data
    ];
}

/**
 * KPI handler registry
 *
 * Maps widget_key → query configuration
 * This is the central place to define simple stat-card widgets.
 */
function getKpiHandler($widgetKey) {
    $handlers = [

        // ── Home Dashboard KPIs ─────────────────────────────────
        'stats_users' => [
            'query' => "SELECT COUNT(*) as value FROM users WHERE deleted_at IS NULL",
            'icon' => 'fas fa-users',
            'color' => 'indigo',
        ],
        'stats_employees' => [
            'query' => "SELECT COUNT(*) as value FROM employees WHERE status = 'active'",
            'icon' => 'fas fa-user-tie',
            'color' => 'emerald',
        ],
        'stats_projects' => [
            'query' => "SELECT COUNT(*) as value FROM projects WHERE status NOT IN ('completed','cancelled')",
            'icon' => 'fas fa-project-diagram',
            'color' => 'violet',
        ],
        'stats_tickets' => [
            'query' => "SELECT COUNT(*) as value FROM tickets WHERE status IN ('open','in_progress')",
            'icon' => 'fas fa-ticket-alt',
            'color' => 'amber',
        ],
        'stats_invoices' => [
            'query' => "SELECT COUNT(*) as value FROM invoices WHERE status IN ('pending','overdue')",
            'icon' => 'fas fa-file-invoice',
            'color' => 'rose',
        ],
        'stats_clients' => [
            'query' => "SELECT COUNT(*) as value FROM clients",
            'icon' => 'fas fa-building',
            'color' => 'cyan',
        ],

        // ── Executive Dashboard KPIs ────────────────────────────
        'ytd_revenue_kpi' => [
            'query' => "SELECT COALESCE(SUM(paid_amount),0) as value FROM invoices WHERE YEAR(invoice_date)=YEAR(CURDATE())",
            'icon' => 'fas fa-dollar-sign',
            'color' => 'green',
            'format' => 'money',
            'trend_query' => "
                SELECT COALESCE((curr - prev) / NULLIF(prev, 0) * 100, 0) as trend,
                       CASE WHEN (curr - prev) >= 0 THEN '▲' ELSE '▼' END as trend_label
                FROM (
                    SELECT SUM(paid_amount) as curr FROM invoices WHERE DATE_FORMAT(invoice_date,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')
                ) c,
                (
                    SELECT SUM(paid_amount) as prev FROM invoices WHERE DATE_FORMAT(invoice_date,'%Y-%m') = DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m')
                ) p
            ",
        ],
        'employee_kpi' => [
            'query' => "SELECT COUNT(*) as value FROM employees WHERE status='active'",
            'icon' => 'fas fa-users',
            'color' => 'blue',
            'trend_query' => "SELECT COUNT(*) as trend FROM employees WHERE joining_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
            'trend_label' => 'new this month',
        ],
        'net_profit_kpi' => [
            'query' => "SELECT COALESCE((SELECT SUM(paid_amount) FROM invoices WHERE YEAR(invoice_date)=YEAR(CURDATE())) - (SELECT SUM(amount) FROM expenses WHERE YEAR(expense_date)=YEAR(CURDATE()) AND status='approved'),0) as value",
            'icon' => 'fas fa-chart-line',
            'color' => 'indigo',
            'format' => 'money',
        ],
        'open_tickets_kpi' => [
            'query' => "SELECT COUNT(*) as value FROM tickets WHERE status IN ('open','in_progress')",
            'icon' => 'fas fa-ticket-alt',
            'color' => 'yellow',
            'trend_query' => "SELECT COUNT(*) as trend FROM tickets WHERE priority='critical' AND status NOT IN ('resolved','closed')",
            'trend_label' => 'critical',
        ],
        'active_projects_kpi' => [
            'query' => "SELECT COUNT(*) as value FROM projects WHERE status='active'",
            'icon' => 'fas fa-project-diagram',
            'color' => 'white',
            'trend_query' => "SELECT COUNT(*) as trend FROM projects WHERE end_date < CURDATE() AND status='active'",
            'trend_label' => 'overdue',
        ],
        'pending_invoices_kpi' => [
            'query' => "SELECT COUNT(*) as value FROM invoices WHERE status IN ('sent','overdue')",
            'icon' => 'fas fa-file-invoice',
            'color' => 'white',
        ],
        'ytd_expenses_kpi' => [
            'query' => "SELECT COALESCE(SUM(amount),0) as value FROM expenses WHERE YEAR(expense_date)=YEAR(CURDATE()) AND status='approved'",
            'icon' => 'fas fa-receipt',
            'color' => 'white',
            'format' => 'money',
        ],
        'overdue_invoices_kpi' => [
            'query' => "SELECT COUNT(*) as value FROM invoices WHERE status='overdue'",
            'icon' => 'fas fa-exclamation-triangle',
            'color' => 'white',
        ],

        // ── Helpdesk KPIs ──────────────────────────────────────
        'hd_open_tickets' => [
            'query' => "SELECT COUNT(*) as value FROM tickets WHERE status='open'",
            'icon' => 'fas fa-envelope-open',
            'color' => 'blue',
        ],
        'hd_in_progress' => [
            'query' => "SELECT COUNT(*) as value FROM tickets WHERE status='in_progress'",
            'icon' => 'fas fa-spinner',
            'color' => 'yellow',
        ],
        'hd_resolved_today' => [
            'query' => "SELECT COUNT(*) as value FROM tickets WHERE status='resolved' AND DATE(resolved_at)=CURDATE()",
            'icon' => 'fas fa-check-circle',
            'color' => 'green',
        ],
        'hd_sla_breached' => [
            'query' => "SELECT COUNT(*) as value FROM tickets WHERE sla_breached=1 AND status NOT IN ('resolved','closed')",
            'icon' => 'fas fa-exclamation-triangle',
            'color' => 'red',
        ],
        'hd_created_today' => [
            'query' => "SELECT COUNT(*) as value FROM tickets WHERE DATE(created_at)=CURDATE()",
            'icon' => 'fas fa-plus-circle',
            'color' => 'white',
        ],
        'hd_waiting_client' => [
            'query' => "SELECT COUNT(*) as value FROM tickets WHERE status='waiting_client'",
            'icon' => 'fas fa-hourglass-half',
            'color' => 'purple',
        ],
        'hd_total_resolved' => [
            'query' => "SELECT COUNT(*) as value FROM tickets WHERE status='resolved'",
            'icon' => 'fas fa-check-double',
            'color' => 'green',
        ],
        'hd_total_tickets' => [
            'query' => "SELECT COUNT(*) as value FROM tickets",
            'icon' => 'fas fa-ticket-alt',
            'color' => 'white',
        ],

        // ── Finance KPIs ───────────────────────────────────────
        'finance_revenue' => [
            'query' => "SELECT COALESCE(SUM(paid_amount),0) as value FROM invoices WHERE status IN ('paid','partially_paid')",
            'icon' => 'fas fa-dollar-sign',
            'color' => 'green',
            'format' => 'money',
        ],
        'finance_expenses' => [
            'query' => "SELECT COALESCE(SUM(amount),0) as value FROM expenses WHERE status='approved'",
            'icon' => 'fas fa-receipt',
            'color' => 'red',
            'format' => 'money',
        ],
        'finance_net_profit' => [
            'query' => "SELECT COALESCE((SELECT SUM(paid_amount) FROM invoices WHERE status IN ('paid','partially_paid')) - (SELECT SUM(amount) FROM expenses WHERE status='approved'),0) as value",
            'icon' => 'fas fa-chart-line',
            'color' => 'indigo',
            'format' => 'money',
        ],
        'finance_pending_invoices' => [
            'query' => "SELECT COALESCE(SUM(balance_amount),0) as value FROM invoices WHERE status IN ('sent','partially_paid','overdue')",
            'icon' => 'fas fa-file-invoice',
            'color' => 'amber',
            'format' => 'money',
        ],

        // ── Leave Management KPIs ──────────────────────────────
        'leave_active_employees' => [
            'query' => "SELECT COUNT(*) as value FROM employees WHERE status='active'",
            'icon' => 'fas fa-users',
            'color' => 'white',
        ],
        'leave_on_leave_today' => [
            'query' => "SELECT COUNT(*) as value FROM leave_requests WHERE status='approved' AND CURDATE() BETWEEN from_date AND to_date",
            'icon' => 'fas fa-calendar-day',
            'color' => 'orange',
        ],
        'leave_pending_approvals' => [
            'query' => "SELECT COUNT(*) as value FROM leave_requests WHERE status='pending'",
            'icon' => 'fas fa-clock',
            'color' => 'yellow',
        ],
        'leave_approved_month' => [
            'query' => "SELECT COUNT(*) as value FROM leave_requests WHERE status='approved' AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())",
            'icon' => 'fas fa-check-circle',
            'color' => 'green',
        ],
        'leave_lop_count' => [
            'query' => "SELECT COUNT(*) as value FROM leave_requests WHERE is_lop=1",
            'icon' => 'fas fa-exclamation-circle',
            'color' => 'red',
        ],
    ];

    return $handlers[$widgetKey] ?? null;
}
