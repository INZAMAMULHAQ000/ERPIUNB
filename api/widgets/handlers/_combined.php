<?php
/**
 * Combined Widget Handlers
 *
 * Contains handler functions for all chart, list, table, and complex widgets.
 * Each function follows the naming convention: widget_{widget_key}
 * Returns standardized JSON response.
 *
 * This file is auto-included by the API dispatcher.
 */

// ════════════════════════════════════════════════════════════════
// HOME DASHBOARD - Module Activity (vertical bar)
// ════════════════════════════════════════════════════════════════
function widget_module_activity($params) {
    $db = getDB();
    $data = $db->query("
        SELECT module, COUNT(*) as cnt
        FROM audit_logs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY module
        ORDER BY cnt DESC
        LIMIT 6
    ")->fetchAll();

    if (empty($data)) {
        return ['success' => true, 'widget_key' => 'module_activity', 'render_type' => 'server_html', 'data' => ['empty' => true, 'message' => 'No activity in the last 7 days']];
    }

    $maxCnt = max(array_column($data, 'cnt'));
    return ['success' => true, 'widget_key' => 'module_activity', 'render_type' => 'server_html', 'data' => [
        'items' => $data,
        'max' => $maxCnt,
        'labels' => array_column($data, 'module'),
        'values' => array_column($data, 'cnt'),
    ]];
}

// ════════════════════════════════════════════════════════════════
// HOME DASHBOARD - Recent Activity (timeline)
// ════════════════════════════════════════════════════════════════
function widget_recent_activity($params) {
    $db = getDB();
    $data = $db->query("
        SELECT al.*, u.name as user_name
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 8
    ")->fetchAll();

    return ['success' => true, 'widget_key' => 'recent_activity', 'render_type' => 'server_html', 'data' => [
        'items' => $data,
        'empty' => empty($data)
    ]];
}

// ════════════════════════════════════════════════════════════════
// HOME DASHBOARD - Pending Items (4 quick stats)
// ════════════════════════════════════════════════════════════════
function widget_pending_items($params) {
    $db = getDB();
    $data = [
        'pending_leaves' => $db->query("SELECT COUNT(*) as c FROM leave_requests WHERE status='pending'")->fetch()['c'],
        'pending_expenses' => $db->query("SELECT COUNT(*) as c FROM expenses WHERE status='pending'")->fetch()['c'],
        'active_assignments' => $db->query("SELECT COUNT(*) as c FROM asset_assignments WHERE status='assigned'")->fetch()['c'],
        'today_attendance' => $db->query("SELECT COUNT(*) as c FROM attendance WHERE attendance_date=CURDATE()")->fetch()['c'],
    ];
    return ['success' => true, 'widget_key' => 'pending_items', 'render_type' => 'server_html', 'data' => $data];
}

// ════════════════════════════════════════════════════════════════
// HOME DASHBOARD - Ticket Status (doughnut chart)
// ════════════════════════════════════════════════════════════════
function widget_ticket_status_chart($params) {
    $db = getDB();
    $data = $db->query("SELECT status, COUNT(*) as cnt FROM tickets GROUP BY status ORDER BY cnt DESC")->fetchAll();

    $colors = ['#f59e0b','#6366f1','#22c55e','#64748b','#ef4444','#8b5cf6'];
    return ['success' => true, 'widget_key' => 'ticket_status_chart', 'render_type' => 'chartjs', 'data' => [
        'labels' => array_map('ucfirst', array_column($data, 'status')),
        'datasets' => [[
            'data' => array_map('intval', array_column($data, 'cnt')),
            'backgroundColor' => array_slice($colors, 0, count($data)),
            'borderWidth' => 0
        ]]
    ], 'chart_type' => 'doughnut', 'chart_options' => []];
}

// ════════════════════════════════════════════════════════════════
// HOME DASHBOARD - Project Status (doughnut chart)
// ════════════════════════════════════════════════════════════════
function widget_project_status_chart($params) {
    $db = getDB();
    $data = $db->query("SELECT status, COUNT(*) as cnt FROM projects GROUP BY status ORDER BY cnt DESC")->fetchAll();

    $colors = ['#8b5cf6','#6366f1','#22c55e','#f59e0b','#ef4444'];
    return ['success' => true, 'widget_key' => 'project_status_chart', 'render_type' => 'chartjs', 'data' => [
        'labels' => array_map('ucfirst', array_column($data, 'status')),
        'datasets' => [[
            'data' => array_map('intval', array_column($data, 'cnt')),
            'backgroundColor' => array_slice($colors, 0, count($data)),
            'borderWidth' => 0
        ]]
    ], 'chart_type' => 'doughnut', 'chart_options' => []];
}

// ════════════════════════════════════════════════════════════════
// HOME DASHBOARD - User Registrations (line chart)
// ════════════════════════════════════════════════════════════════
function widget_user_registrations_chart($params) {
    $db = getDB();
    $data = $db->query("
        SELECT DATE_FORMAT(created_at, '%b') as label, COUNT(*) as cnt
        FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY MIN(created_at)
    ")->fetchAll();

    return ['success' => true, 'widget_key' => 'user_registrations_chart', 'render_type' => 'chartjs', 'data' => [
        'labels' => array_column($data, 'label'),
        'datasets' => [[
            'label' => 'New Users',
            'data' => array_map('intval', array_column($data, 'cnt')),
            'borderColor' => '#6366f1',
            'backgroundColor' => 'rgba(99,102,241,0.08)',
            'fill' => true,
            'tension' => 0.3
        ]]
    ], 'chart_type' => 'line', 'chart_options' => []];
}

// ════════════════════════════════════════════════════════════════
// EXECUTIVE DASHBOARD - Revenue Trend (line chart)
// ════════════════════════════════════════════════════════════════
function widget_revenue_trend($params) {
    $db = getDB();
    $data = $db->query("
        SELECT DATE_FORMAT(invoice_date,'%b %Y') AS label, SUM(paid_amount) AS value
        FROM invoices WHERE invoice_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(invoice_date,'%Y-%m') ORDER BY invoice_date ASC
    ")->fetchAll();

    return ['success' => true, 'widget_key' => 'revenue_trend', 'render_type' => 'chartjs', 'data' => [
        'labels' => array_column($data, 'label'),
        'datasets' => [[
            'label' => 'Revenue',
            'data' => array_map('floatval', array_column($data, 'value')),
            'borderColor' => '#10b981',
            'backgroundColor' => 'rgba(16,185,129,0.1)',
            'fill' => true,
            'tension' => 0.4,
            'pointRadius' => 4
        ]]
    ], 'chart_type' => 'line', 'chart_options' => []];
}

// ════════════════════════════════════════════════════════════════
// EXECUTIVE DASHBOARD - Project Status Doughnut
// ════════════════════════════════════════════════════════════════
function widget_project_status_doughnut($params) {
    $db = getDB();
    $data = $db->query("SELECT status, COUNT(*) as cnt FROM projects GROUP BY status")->fetchAll();
    $colors = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6'];

    return ['success' => true, 'widget_key' => 'project_status_doughnut', 'render_type' => 'chartjs', 'data' => [
        'labels' => array_map('ucfirst', array_column($data, 'status')),
        'datasets' => [[
            'data' => array_map('intval', array_column($data, 'cnt')),
            'backgroundColor' => array_slice($colors, 0, count($data))
        ]]
    ], 'chart_type' => 'doughnut', 'chart_options' => []];
}

// ════════════════════════════════════════════════════════════════
// EXECUTIVE DASHBOARD - Top Clients
// ════════════════════════════════════════════════════════════════
function widget_top_clients($params) {
    $db = getDB();
    $data = $db->query("
        SELECT c.company_name AS name, SUM(i.paid_amount) AS revenue
        FROM clients c JOIN invoices i ON i.client_id=c.id
        WHERE YEAR(i.invoice_date)=YEAR(CURDATE())
        GROUP BY c.id ORDER BY revenue DESC LIMIT 5
    ")->fetchAll();

    $maxRev = !empty($data) ? max(array_column($data, 'revenue')) : 0;
    return ['success' => true, 'widget_key' => 'top_clients', 'render_type' => 'server_html', 'data' => [
        'items' => $data,
        'max_revenue' => $maxRev,
        'empty' => empty($data)
    ]];
}

// ════════════════════════════════════════════════════════════════
// EXECUTIVE DASHBOARD - Pending Leave Requests
// ════════════════════════════════════════════════════════════════
function widget_pending_leave_requests($params) {
    $db = getDB();
    $data = $db->query("
        SELECT CONCAT(e.first_name,' ',e.last_name) AS name, lt.leave_name AS leave_type,
               lr.from_date AS start_date, lr.to_date AS end_date
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id=e.id
        LEFT JOIN leave_types lt ON lr.leave_type_id=lt.id
        WHERE lr.status='pending' ORDER BY lr.created_at DESC LIMIT 5
    ")->fetchAll();

    return ['success' => true, 'widget_key' => 'pending_leave_requests', 'render_type' => 'server_html', 'data' => [
        'items' => $data,
        'empty' => empty($data)
    ]];
}

// ════════════════════════════════════════════════════════════════
// EXECUTIVE DASHBOARD - Recent Activity
// ════════════════════════════════════════════════════════════════
function widget_exec_recent_activity($params) {
    $db = getDB();
    $data = $db->query("
        SELECT al.action, al.module, u.name AS user_name, al.created_at
        FROM audit_logs al LEFT JOIN users u ON al.user_id=u.id
        ORDER BY al.created_at DESC LIMIT 8
    ")->fetchAll();

    return ['success' => true, 'widget_key' => 'exec_recent_activity', 'render_type' => 'server_html', 'data' => [
        'items' => $data,
        'empty' => empty($data)
    ]];
}

// ════════════════════════════════════════════════════════════════
// HELPDESK - Priority Breakdown
// ════════════════════════════════════════════════════════════════
function widget_hd_priority_breakdown($params) {
    $db = getDB();
    $data = [
        ['label' => 'Critical', 'count' => $db->query("SELECT COUNT(*) as c FROM tickets WHERE priority='critical' AND status NOT IN ('resolved','closed')")->fetch()['c'], 'color' => 'bg-red-500', 'text' => 'text-red-600'],
        ['label' => 'High', 'count' => $db->query("SELECT COUNT(*) as c FROM tickets WHERE priority='high' AND status NOT IN ('resolved','closed')")->fetch()['c'], 'color' => 'bg-orange-500', 'text' => 'text-orange-600'],
        ['label' => 'Medium', 'count' => $db->query("SELECT COUNT(*) as c FROM tickets WHERE priority='medium' AND status NOT IN ('resolved','closed')")->fetch()['c'], 'color' => 'bg-blue-500', 'text' => 'text-blue-600'],
        ['label' => 'Low', 'count' => $db->query("SELECT COUNT(*) as c FROM tickets WHERE priority='low' AND status NOT IN ('resolved','closed')")->fetch()['c'], 'color' => 'bg-gray-400', 'text' => 'text-gray-600'],
    ];
    $maxPriority = max(1, array_sum(array_column($data, 'count')));

    return ['success' => true, 'widget_key' => 'hd_priority_breakdown', 'render_type' => 'server_html', 'data' => [
        'items' => $data,
        'max' => $maxPriority
    ]];
}

// ════════════════════════════════════════════════════════════════
// HELPDESK - SLA Alerts
// ════════════════════════════════════════════════════════════════
function widget_hd_sla_alerts($params) {
    $db = getDB();
    $data = $db->query("
        SELECT t.ticket_number, t.subject, t.priority, t.due_date, t.status,
               sla.sla_name, sla.resolution_time_hours,
               TIMESTAMPDIFF(HOUR, t.created_at, NOW()) as hours_elapsed
        FROM tickets t
        JOIN slas sla ON t.sla_id = sla.id
        WHERE t.sla_breached = 0 AND t.status NOT IN ('resolved', 'closed')
        AND TIMESTAMPDIFF(HOUR, t.created_at, NOW()) >= (sla.resolution_time_hours * 0.8)
        ORDER BY hours_elapsed DESC LIMIT 5
    ")->fetchAll();

    return ['success' => true, 'widget_key' => 'hd_sla_alerts', 'render_type' => 'server_html', 'data' => [
        'items' => $data,
        'empty' => empty($data)
    ]];
}

// ════════════════════════════════════════════════════════════════
// HELPDESK - Pending Client Responses
// ════════════════════════════════════════════════════════════════
function widget_hd_pending_responses($params) {
    $db = getDB();
    $data = $db->query("
        SELECT t.ticket_number, t.subject, t.status, t.created_at,
               cl.company_name as client_name
        FROM tickets t LEFT JOIN clients cl ON t.client_id = cl.id
        WHERE t.status = 'waiting_client'
        ORDER BY t.created_at ASC LIMIT 5
    ")->fetchAll();

    return ['success' => true, 'widget_key' => 'hd_pending_responses', 'render_type' => 'server_html', 'data' => [
        'items' => $data,
        'empty' => empty($data)
    ]];
}

// ════════════════════════════════════════════════════════════════
// HELPDESK - Recent Tickets
// ════════════════════════════════════════════════════════════════
function widget_hd_recent_tickets($params) {
    $db = getDB();
    $data = $db->query("
        SELECT t.ticket_number, t.subject, t.priority, t.status, t.created_at,
               e.first_name, e.last_name, tc.category_name
        FROM tickets t
        LEFT JOIN employees e ON t.assigned_to = e.id
        LEFT JOIN ticket_categories tc ON t.category_id = tc.id
        ORDER BY t.created_at DESC LIMIT 10
    ")->fetchAll();

    $priorityColors = ['low' => 'bg-gray-100 text-gray-700', 'medium' => 'bg-blue-100 text-blue-800', 'high' => 'bg-orange-100 text-orange-800', 'critical' => 'bg-red-100 text-red-800'];
    $statusColors = ['open' => 'bg-blue-100 text-blue-800', 'in_progress' => 'bg-yellow-100 text-yellow-800', 'waiting_client' => 'bg-purple-100 text-purple-800', 'resolved' => 'bg-green-100 text-green-800', 'closed' => 'bg-gray-100 text-gray-800', 'reopened' => 'bg-red-100 text-red-800'];

    return ['success' => true, 'widget_key' => 'hd_recent_tickets', 'render_type' => 'server_html', 'data' => [
        'items' => $data,
        'priority_colors' => $priorityColors,
        'status_colors' => $statusColors,
        'empty' => empty($data)
    ]];
}

// ════════════════════════════════════════════════════════════════
// HELPDESK - Top Agents
// ════════════════════════════════════════════════════════════════
function widget_hd_top_agents($params) {
    $db = getDB();
    $data = $db->query("
        SELECT e.first_name, e.last_name, COUNT(t.id) as ticket_count,
               SUM(CASE WHEN t.status = 'resolved' THEN 1 ELSE 0 END) as resolved_count
        FROM tickets t
        JOIN employees e ON t.assigned_to = e.id
        WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY t.assigned_to
        ORDER BY resolved_count DESC LIMIT 5
    ")->fetchAll();

    return ['success' => true, 'widget_key' => 'hd_top_agents', 'render_type' => 'server_html', 'data' => [
        'items' => $data,
        'empty' => empty($data)
    ]];
}

// ════════════════════════════════════════════════════════════════
// HELPDESK - Category Stats
// ════════════════════════════════════════════════════════════════
function widget_hd_category_stats($params) {
    $db = getDB();
    $data = $db->query("
        SELECT tc.category_name, tc.category_code, COUNT(t.id) as ticket_count,
               SUM(CASE WHEN t.status = 'open' THEN 1 ELSE 0 END) as open_count
        FROM ticket_categories tc
        LEFT JOIN tickets t ON tc.id = t.category_id
        GROUP BY tc.id
        ORDER BY ticket_count DESC
    ")->fetchAll();

    return ['success' => true, 'widget_key' => 'hd_category_stats', 'render_type' => 'server_html', 'data' => [
        'items' => $data,
        'empty' => empty($data)
    ]];
}

// ════════════════════════════════════════════════════════════════
// HELPDESK - Quick Actions
// ════════════════════════════════════════════════════════════════
function widget_hd_quick_actions($params) {
    $actions = [];

    if (hasPermission('tickets.create')) {
        $actions[] = ['label' => 'New Ticket', 'icon' => 'fas fa-plus-circle', 'color' => 'blue', 'url' => url('modules/tickets/create.php'), 'desc' => 'Create support ticket'];
    }
    if (hasPermission('tickets.view')) {
        $actions[] = ['label' => 'All Tickets', 'icon' => 'fas fa-list', 'color' => 'green', 'url' => url('modules/tickets/index.php'), 'desc' => 'View ticket list'];
    }
    if (hasPermission('ticket_categories.view')) {
        $actions[] = ['label' => 'Categories', 'icon' => 'fas fa-tags', 'color' => 'purple', 'url' => url('modules/ticket_categories/index.php'), 'desc' => 'Manage categories'];
    }
    if (hasPermission('slas.view')) {
        $actions[] = ['label' => 'SLA Policies', 'icon' => 'fas fa-clock', 'color' => 'orange', 'url' => url('modules/slas/index.php'), 'desc' => 'Manage SLAs'];
    }
    if (hasPermission('knowledgebase.view')) {
        $actions[] = ['label' => 'Knowledge Base', 'icon' => 'fas fa-book', 'color' => 'teal', 'url' => url('modules/knowledge_base/index.php'), 'desc' => 'Browse articles'];
    }
    if (hasPermission('knowledgebase.create')) {
        $actions[] = ['label' => 'New Article', 'icon' => 'fas fa-file-alt', 'color' => 'indigo', 'url' => url('modules/knowledge_base/create.php'), 'desc' => 'Write KB article'];
    }

    return ['success' => true, 'widget_key' => 'hd_quick_actions', 'render_type' => 'server_html', 'data' => ['items' => $actions]];
}

// ════════════════════════════════════════════════════════════════
// FINANCE - Revenue Trend (line chart, 6 months)
// ════════════════════════════════════════════════════════════════
function widget_finance_revenue_trend($params) {
    $db = getDB();
    $data = $db->query("
        SELECT DATE_FORMAT(invoice_date, '%Y-%m') as month, SUM(paid_amount) as total
        FROM invoices
        WHERE invoice_date >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH) AND status IN ('paid', 'partially_paid')
        GROUP BY DATE_FORMAT(invoice_date, '%Y-%m') ORDER BY month
    ")->fetchAll();

    return ['success' => true, 'widget_key' => 'finance_revenue_trend', 'render_type' => 'chartjs', 'data' => [
        'labels' => array_column($data, 'month'),
        'datasets' => [[
            'label' => 'Revenue',
            'data' => array_map('floatval', array_column($data, 'total')),
            'borderColor' => '#10b981',
            'backgroundColor' => 'rgba(16,185,129,0.1)',
            'fill' => true,
            'tension' => 0.3
        ]]
    ], 'chart_type' => 'line', 'chart_options' => []];
}

// ════════════════════════════════════════════════════════════════
// FINANCE - Expenses by Category
// ════════════════════════════════════════════════════════════════
function widget_finance_expense_categories($params) {
    $db = getDB();
    $data = $db->query("
        SELECT c.category_name, SUM(e.amount) as total
        FROM expenses e JOIN expense_categories c ON e.category_id = c.id
        WHERE e.status = 'approved'
        GROUP BY c.id, c.category_name ORDER BY total DESC LIMIT 5
    ")->fetchAll();

    $grandTotal = array_sum(array_column($data, 'total'));
    return ['success' => true, 'widget_key' => 'finance_expense_categories', 'render_type' => 'server_html', 'data' => [
        'items' => $data,
        'grand_total' => $grandTotal,
        'empty' => empty($data)
    ]];
}

// ════════════════════════════════════════════════════════════════
// FINANCE - Recent Invoices
// ════════════════════════════════════════════════════════════════
function widget_finance_recent_invoices($params) {
    $db = getDB();
    $data = $db->query("
        SELECT i.*, c.company_name
        FROM invoices i LEFT JOIN clients c ON i.client_id = c.id
        ORDER BY i.created_at DESC LIMIT 5
    ")->fetchAll();

    return ['success' => true, 'widget_key' => 'finance_recent_invoices', 'render_type' => 'server_html', 'data' => [
        'items' => $data,
        'empty' => empty($data)
    ]];
}

// ════════════════════════════════════════════════════════════════
// FINANCE - Recent Payments
// ════════════════════════════════════════════════════════════════
function widget_finance_recent_payments($params) {
    $db = getDB();
    $data = $db->query("
        SELECT p.*, i.invoice_number, c.company_name
        FROM payments p JOIN invoices i ON p.invoice_id = i.id
        LEFT JOIN clients c ON i.client_id = c.id
        ORDER BY p.created_at DESC LIMIT 5
    ")->fetchAll();

    return ['success' => true, 'widget_key' => 'finance_recent_payments', 'render_type' => 'server_html', 'data' => [
        'items' => $data,
        'empty' => empty($data)
    ]];
}

// ════════════════════════════════════════════════════════════════
// LEAVE - Pending Approvals Alert
// ════════════════════════════════════════════════════════════════
function widget_leave_pending_alert($params) {
    $db = getDB();
    $pendingApprovals = $db->query("SELECT COUNT(*) as c FROM leave_requests WHERE status='pending'")->fetch()['c'];
    $canApprove = hasPermission('leaves.approve');

    return ['success' => true, 'widget_key' => 'leave_pending_alert', 'render_type' => 'server_html', 'data' => [
        'count' => $pendingApprovals,
        'can_approve' => $canApprove,
        'has_alerts' => $pendingApprovals > 0 && $canApprove
    ]];
}

// ════════════════════════════════════════════════════════════════
// LEAVE - Employee Leave Balances
// ════════════════════════════════════════════════════════════════
function widget_leave_employee_balances($params) {
    $db = getDB();
    $employeeId = $params['employee_id'] ?? '';

    $where = "WHERE e.status = 'active'";
    $params_arr = [];
    if ($employeeId) {
        $where .= " AND e.id = ?";
        $params_arr[] = $employeeId;
    }

    $empQuery = $db->prepare("
        SELECT e.id, e.first_name, e.last_name, e.employee_code, d.department_name
        FROM employees e LEFT JOIN departments d ON e.department_id = d.id
        $where ORDER BY e.first_name ASC LIMIT 50
    ");
    $empQuery->execute($params_arr);
    $employees = $empQuery->fetchAll();

    $employeeBalances = [];
    foreach ($employees as $emp) {
        $balStmt = $db->prepare("
            SELECT lt.leave_name, lt.leave_code, COALESCE(lb.total_days, lt.total_days) as allocated,
                   COALESCE(lb.used_days, 0) as used
            FROM leave_types lt
            LEFT JOIN leave_balances lb ON lb.leave_type_id = lt.id AND lb.employee_id = ? AND lb.year = YEAR(CURDATE())
            WHERE lt.status = 'active' ORDER BY lt.leave_name
        ");
        $balStmt->execute([$emp['id']]);
        $balances = $balStmt->fetchAll();

        $totalAllocated = array_sum(array_column($balances, 'allocated'));
        $totalUsed = array_sum(array_column($balances, 'used'));
        $totalRemaining = $totalAllocated - $totalUsed;

        $employeeBalances[] = [
            'info' => $emp,
            'balances' => $balances,
            'total_allocated' => $totalAllocated,
            'total_used' => $totalUsed,
            'total_remaining' => $totalRemaining,
        ];
    }

    $allTypes = $db->query("SELECT id, leave_name, leave_code FROM leave_types WHERE status = 'active' ORDER BY leave_name")->fetchAll();

    return ['success' => true, 'widget_key' => 'leave_employee_balances', 'render_type' => 'server_html', 'data' => [
        'employees' => $employeeBalances,
        'leave_types' => $allTypes,
        'empty' => empty($employeeBalances)
    ]];
}

// ════════════════════════════════════════════════════════════════
// ANALYTICS - HR Analytics
// ════════════════════════════════════════════════════════════════
function widget_analytics_hr($params) {
    $db = getDB();
    $period = $params['period'] ?? '30';
    $dateFrom = date('Y-m-d', strtotime("-{$period} days"));

    $totalEmp = $db->query("SELECT COUNT(*) FROM employees WHERE status='active'")->fetchColumn();
    $newEmp = $db->prepare("SELECT COUNT(*) FROM employees WHERE joining_date >= ?");
    $newEmp->execute([$dateFrom]);
    $avgAttPct = $db->prepare("SELECT ROUND(AVG(sub.pct),1) FROM (SELECT ROUND(SUM(status='present')/COUNT(*)*100,1) AS pct FROM attendance WHERE attendance_date >= ? GROUP BY employee_id) sub");
    $avgAttPct->execute([$dateFrom]);
    $openLeaves = $db->query("SELECT COUNT(*) FROM leave_requests WHERE status='pending'")->fetchColumn();

    return ['success' => true, 'widget_key' => 'analytics_hr', 'render_type' => 'server_html', 'data' => [
        'total_employees' => $totalEmp,
        'new_hires' => $newEmp->fetchColumn(),
        'avg_attendance' => $avgAttPct->fetchColumn() ?? 0,
        'pending_leaves' => $openLeaves
    ]];
}

// ════════════════════════════════════════════════════════════════
// ANALYTICS - Project Analytics
// ════════════════════════════════════════════════════════════════
function widget_analytics_projects($params) {
    $db = getDB();
    $period = $params['period'] ?? '30';
    $dateFrom = date('Y-m-d', strtotime("-{$period} days"));

    return ['success' => true, 'widget_key' => 'analytics_projects', 'render_type' => 'server_html', 'data' => [
        'active_projects' => $db->query("SELECT COUNT(*) FROM projects WHERE status='active'")->fetchColumn(),
        'delayed_tasks' => $db->query("SELECT COUNT(*) FROM tasks WHERE due_date < CURDATE() AND status NOT IN ('completed','cancelled')")->fetchColumn(),
        'completed_tasks' => $db->prepare("SELECT COUNT(*) FROM tasks WHERE status='completed' AND updated_at >= ?")->execute([$dateFrom])->fetchColumn(),
    ]];
}

// ════════════════════════════════════════════════════════════════
// ANALYTICS - CRM Analytics
// ════════════════════════════════════════════════════════════════
function widget_analytics_crm($params) {
    $db = getDB();
    $period = $params['period'] ?? '30';
    $dateFrom = date('Y-m-d', strtotime("-{$period} days"));

    $totalLeads = $db->prepare("SELECT COUNT(*) FROM leads WHERE created_at >= ?");
    $totalLeads->execute([$dateFrom]);
    $convertedLeads = $db->prepare("SELECT COUNT(*) FROM leads WHERE status='won' AND created_at >= ?");
    $convertedLeads->execute([$dateFrom]);
    $convRate = $totalLeads->fetchColumn() > 0 ? round($convertedLeads->fetchColumn() / $totalLeads->fetchColumn() * 100, 1) : 0;

    return ['success' => true, 'widget_key' => 'analytics_crm', 'render_type' => 'server_html', 'data' => [
        'total_leads' => $totalLeads->fetchColumn(),
        'conversion_rate' => $convRate,
        'active_clients' => $db->query("SELECT COUNT(*) FROM clients WHERE status='active'")->fetchColumn()
    ]];
}

// ════════════════════════════════════════════════════════════════
// ANALYTICS - Finance Analytics
// ════════════════════════════════════════════════════════════════
function widget_analytics_finance($params) {
    $db = getDB();
    $period = $params['period'] ?? '30';
    $dateFrom = date('Y-m-d', strtotime("-{$period} days"));

    $revenue = $db->prepare("SELECT COALESCE(SUM(paid_amount),0) FROM invoices WHERE invoice_date >= ?");
    $revenue->execute([$dateFrom]);
    $expenses = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date >= ? AND status='approved'");
    $expenses->execute([$dateFrom]);

    return ['success' => true, 'widget_key' => 'analytics_finance', 'render_type' => 'server_html', 'data' => [
        'revenue' => $revenue->fetchColumn(),
        'expenses' => $expenses->fetchColumn(),
        'profit' => $revenue->fetchColumn() - $expenses->fetchColumn(),
        'pending_invoices' => $db->query("SELECT COUNT(*) FROM invoices WHERE status IN ('sent','overdue')")->fetchColumn()
    ]];
}

// ════════════════════════════════════════════════════════════════
// ANALYTICS - Helpdesk Analytics
// ════════════════════════════════════════════════════════════════
function widget_analytics_tickets($params) {
    $db = getDB();
    $period = $params['period'] ?? '30';
    $dateFrom = date('Y-m-d', strtotime("-{$period} days"));

    $openTickets = $db->query("SELECT COUNT(*) FROM tickets WHERE status IN ('open','in_progress')")->fetchColumn();
    $resolvedTickets = $db->prepare("SELECT COUNT(*) FROM tickets WHERE status='resolved' AND resolved_at >= ?");
    $resolvedTickets->execute([$dateFrom]);
    $totalTickets = $db->prepare("SELECT COUNT(*) FROM tickets WHERE created_at >= ?");
    $totalTickets->execute([$dateFrom]);
    $resolutionRate = $totalTickets->fetchColumn() > 0 ? round($resolvedTickets->fetchColumn() / $totalTickets->fetchColumn() * 100, 1) : 0;

    return ['success' => true, 'widget_key' => 'analytics_tickets', 'render_type' => 'server_html', 'data' => [
        'open_tickets' => $openTickets,
        'resolution_rate' => $resolutionRate,
        'total_tickets' => $totalTickets->fetchColumn()
    ]];
}
