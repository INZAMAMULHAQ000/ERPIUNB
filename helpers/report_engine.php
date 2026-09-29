<?php
/**
 * Report Engine — Phase 10
 * All queries verified against actual DB schema.
 */

function getReportTitle(string $type): string {
    $titles = [
        'employee_summary'    => 'Employee Summary',
        'attendance_report'   => 'Attendance Report',
        'leave_summary'       => 'Leave Summary',
        'project_progress'    => 'Project Progress',
        'task_completion'     => 'Task Completion',
        'timesheet_summary'   => 'Timesheet Summary',
        'leads_conversion'    => 'Leads Conversion',
        'sales_pipeline'      => 'Sales Pipeline',
        'client_growth'       => 'Client Growth',
        'revenue_summary'     => 'Revenue Summary',
        'expense_summary'     => 'Expense Summary',
        'profit_overview'     => 'Profit Overview',
        'invoice_aging'       => 'Invoice Aging',
        'asset_allocation'    => 'Asset Allocation',
        'maintenance_summary' => 'Maintenance Summary',
        'license_expiry'      => 'License Expiry',
        'ticket_resolution'   => 'Ticket Resolution',
        'sla_performance'     => 'SLA Performance',
        'support_workload'    => 'Support Workload',
    ];
    return $titles[$type] ?? ucwords(str_replace('_', ' ', $type));
}

function getReportColumns(string $type): array {
    $map = [
        'employee_summary' => [
            ['key'=>'employee_code',   'label'=>'Code'],
            ['key'=>'full_name',       'label'=>'Name'],
            ['key'=>'department',      'label'=>'Department'],
            ['key'=>'designation',     'label'=>'Designation'],
            ['key'=>'employment_type', 'label'=>'Type'],
            ['key'=>'joining_date',    'label'=>'Join Date'],
            ['key'=>'status',          'label'=>'Status'],
        ],
        'attendance_report' => [
            ['key'=>'full_name',       'label'=>'Employee'],
            ['key'=>'department',      'label'=>'Department'],
            ['key'=>'total_days',      'label'=>'Total Days'],
            ['key'=>'present',         'label'=>'Present'],
            ['key'=>'absent',          'label'=>'Absent'],
            ['key'=>'late',            'label'=>'Late'],
            ['key'=>'attendance_pct',  'label'=>'Attendance %'],
        ],
        'leave_summary' => [
            ['key'=>'full_name',       'label'=>'Employee'],
            ['key'=>'department',      'label'=>'Department'],
            ['key'=>'leave_type',      'label'=>'Leave Type'],
            ['key'=>'total_days',      'label'=>'Days Taken'],
            ['key'=>'status',          'label'=>'Status'],
        ],
        'project_progress' => [
            ['key'=>'project_name',    'label'=>'Project'],
            ['key'=>'client_name',     'label'=>'Client'],
            ['key'=>'start_date',      'label'=>'Start'],
            ['key'=>'end_date',        'label'=>'End'],
            ['key'=>'total_tasks',     'label'=>'Tasks'],
            ['key'=>'completed',       'label'=>'Completed'],
            ['key'=>'progress_pct',    'label'=>'Progress %'],
            ['key'=>'status',          'label'=>'Status'],
        ],
        'task_completion' => [
            ['key'=>'task_title',      'label'=>'Task'],
            ['key'=>'project_name',    'label'=>'Project'],
            ['key'=>'assigned_to',     'label'=>'Assigned To'],
            ['key'=>'priority',        'label'=>'Priority'],
            ['key'=>'due_date',        'label'=>'Due Date'],
            ['key'=>'status',          'label'=>'Status'],
        ],
        'timesheet_summary' => [
            ['key'=>'full_name',       'label'=>'Employee'],
            ['key'=>'project_name',    'label'=>'Project'],
            ['key'=>'total_hours',     'label'=>'Total Hours'],
            ['key'=>'work_date',       'label'=>'Date'],
        ],
        'leads_conversion' => [
            ['key'=>'company_name',    'label'=>'Company'],
            ['key'=>'contact_person',  'label'=>'Contact'],
            ['key'=>'source',          'label'=>'Source'],
            ['key'=>'status',          'label'=>'Status'],
            ['key'=>'assigned_to',     'label'=>'Assigned To'],
            ['key'=>'created_at',      'label'=>'Created'],
        ],
        'sales_pipeline' => [
            ['key'=>'deal_title',      'label'=>'Deal'],
            ['key'=>'client_name',     'label'=>'Client'],
            ['key'=>'deal_value',      'label'=>'Value'],
            ['key'=>'stage',           'label'=>'Stage'],
            ['key'=>'probability',     'label'=>'Probability %'],
            ['key'=>'expected_close',  'label'=>'Expected Close'],
        ],
        'client_growth' => [
            ['key'=>'company_name',    'label'=>'Client'],
            ['key'=>'industry',        'label'=>'Industry'],
            ['key'=>'country',         'label'=>'Country'],
            ['key'=>'total_invoices',  'label'=>'Invoices'],
            ['key'=>'total_revenue',   'label'=>'Revenue'],
            ['key'=>'created_at',      'label'=>'Since'],
        ],
        'revenue_summary' => [
            ['key'=>'month',           'label'=>'Month'],
            ['key'=>'invoices',        'label'=>'Invoices'],
            ['key'=>'total_invoiced',  'label'=>'Invoiced'],
            ['key'=>'total_paid',      'label'=>'Paid'],
            ['key'=>'outstanding',     'label'=>'Outstanding'],
        ],
        'expense_summary' => [
            ['key'=>'category',        'label'=>'Category'],
            ['key'=>'employee',        'label'=>'Employee'],
            ['key'=>'amount',          'label'=>'Amount'],
            ['key'=>'expense_date',    'label'=>'Date'],
            ['key'=>'status',          'label'=>'Status'],
        ],
        'profit_overview' => [
            ['key'=>'month',           'label'=>'Month'],
            ['key'=>'revenue',         'label'=>'Revenue'],
            ['key'=>'expenses',        'label'=>'Expenses'],
            ['key'=>'profit',          'label'=>'Profit'],
            ['key'=>'margin_pct',      'label'=>'Margin %'],
        ],
        'invoice_aging' => [
            ['key'=>'invoice_number',  'label'=>'Invoice #'],
            ['key'=>'client_name',     'label'=>'Client'],
            ['key'=>'total_amount',    'label'=>'Amount'],
            ['key'=>'paid_amount',     'label'=>'Paid'],
            ['key'=>'balance_amount',  'label'=>'Balance'],
            ['key'=>'due_date',        'label'=>'Due Date'],
            ['key'=>'days_overdue',    'label'=>'Days Overdue'],
            ['key'=>'status',          'label'=>'Status'],
        ],
        'asset_allocation' => [
            ['key'=>'asset_name',      'label'=>'Asset'],
            ['key'=>'asset_tag',       'label'=>'Tag'],
            ['key'=>'category',        'label'=>'Category'],
            ['key'=>'assigned_to',     'label'=>'Assigned To'],
            ['key'=>'assigned_date',   'label'=>'Assigned Date'],
            ['key'=>'status',          'label'=>'Status'],
        ],
        'maintenance_summary' => [
            ['key'=>'asset_name',      'label'=>'Asset'],
            ['key'=>'maintenance_type','label'=>'Type'],
            ['key'=>'maintenance_date','label'=>'Date'],
            ['key'=>'cost',            'label'=>'Cost'],
            ['key'=>'status',          'label'=>'Status'],
        ],
        'license_expiry' => [
            ['key'=>'software_name',   'label'=>'Software'],
            ['key'=>'license_key',     'label'=>'License Key'],
            ['key'=>'seats',           'label'=>'Seats'],
            ['key'=>'expiry_date',     'label'=>'Expiry Date'],
            ['key'=>'days_left',       'label'=>'Days Left'],
            ['key'=>'status',          'label'=>'Status'],
        ],
        'ticket_resolution' => [
            ['key'=>'ticket_number',   'label'=>'Ticket #'],
            ['key'=>'subject',         'label'=>'Subject'],
            ['key'=>'requester',       'label'=>'Requester'],
            ['key'=>'assigned_to',     'label'=>'Agent'],
            ['key'=>'priority',        'label'=>'Priority'],
            ['key'=>'status',          'label'=>'Status'],
            ['key'=>'created_at',      'label'=>'Created'],
            ['key'=>'resolved_at',     'label'=>'Resolved'],
        ],
        'sla_performance' => [
            ['key'=>'ticket_number',   'label'=>'Ticket #'],
            ['key'=>'subject',         'label'=>'Subject'],
            ['key'=>'sla_name',        'label'=>'SLA'],
            ['key'=>'priority',        'label'=>'Priority'],
            ['key'=>'response_time',   'label'=>'Response (hrs)'],
            ['key'=>'resolution_time', 'label'=>'Resolution (hrs)'],
            ['key'=>'sla_status',      'label'=>'SLA Status'],
        ],
        'support_workload' => [
            ['key'=>'agent_name',      'label'=>'Agent'],
            ['key'=>'open_tickets',    'label'=>'Open'],
            ['key'=>'resolved',        'label'=>'Resolved'],
            ['key'=>'total',           'label'=>'Total'],
            ['key'=>'avg_resolution',  'label'=>'Avg Resolution (hrs)'],
        ],
    ];
    return $map[$type] ?? [];
}

function getReportData(PDO $db, string $type, array $f): array {
    $from   = $f['date_from']     ?? date('Y-m-01');
    $to     = $f['date_to']       ?? date('Y-m-d');
    $deptId = $f['department_id'] ?? '';
    $status = $f['status']        ?? '';

    switch ($type) {

        case 'employee_summary':
            $w = "WHERE 1=1"; $p = [];
            if ($deptId) { $w .= " AND e.department_id=?"; $p[] = $deptId; }
            if ($status)  { $w .= " AND e.status=?";        $p[] = $status; }
            $stmt = $db->prepare("
                SELECT e.employee_code,
                       CONCAT(e.first_name,' ',e.last_name) AS full_name,
                       d.department_name AS department,
                       des.designation_name AS designation,
                       e.employment_type, e.joining_date, e.status
                FROM employees e
                LEFT JOIN departments d   ON e.department_id=d.id
                LEFT JOIN designations des ON e.designation_id=des.id
                $w ORDER BY e.first_name");
            $stmt->execute($p);
            return $stmt->fetchAll();

        case 'attendance_report':
            $w = "WHERE a.attendance_date BETWEEN ? AND ?"; $p = [$from, $to];
            if ($deptId) { $w .= " AND e.department_id=?"; $p[] = $deptId; }
            $stmt = $db->prepare("
                SELECT CONCAT(e.first_name,' ',e.last_name) AS full_name,
                       d.department_name AS department,
                       COUNT(*) AS total_days,
                       SUM(a.status='present') AS present,
                       SUM(a.status='absent')  AS absent,
                       SUM(a.status='late')    AS late,
                       ROUND(SUM(a.status='present')/COUNT(*)*100,1) AS attendance_pct
                FROM attendance a
                JOIN employees e ON a.employee_id=e.id
                LEFT JOIN departments d ON e.department_id=d.id
                $w GROUP BY e.id ORDER BY full_name");
            $stmt->execute($p);
            return $stmt->fetchAll();

        case 'leave_summary':
            $w = "WHERE lr.from_date BETWEEN ? AND ?"; $p = [$from, $to];
            if ($deptId) { $w .= " AND e.department_id=?"; $p[] = $deptId; }
            if ($status)  { $w .= " AND lr.status=?";       $p[] = $status; }
            $stmt = $db->prepare("
                SELECT CONCAT(e.first_name,' ',e.last_name) AS full_name,
                       d.department_name AS department,
                       lt.leave_name AS leave_type,
                       lr.total_days,
                       lr.status
                FROM leave_requests lr
                JOIN employees e ON lr.employee_id=e.id
                LEFT JOIN departments d  ON e.department_id=d.id
                LEFT JOIN leave_types lt ON lr.leave_type_id=lt.id
                $w ORDER BY lr.from_date DESC");
            $stmt->execute($p);
            return $stmt->fetchAll();

        case 'project_progress':
            $w = "WHERE 1=1"; $p = [];
            if ($status) { $w .= " AND p.status=?"; $p[] = $status; }
            $stmt = $db->prepare("
                SELECT p.project_name,
                       p.client_name,
                       p.start_date, p.end_date,
                       COUNT(t.id) AS total_tasks,
                       SUM(t.status='completed') AS completed,
                       CASE WHEN COUNT(t.id)>0
                            THEN ROUND(SUM(t.status='completed')/COUNT(t.id)*100,1)
                            ELSE 0 END AS progress_pct,
                       p.status
                FROM projects p
                LEFT JOIN tasks t ON t.project_id=p.id
                $w GROUP BY p.id ORDER BY p.start_date DESC");
            $stmt->execute($p);
            return $stmt->fetchAll();

        case 'task_completion':
            $w = "WHERE 1=1"; $p = [];
            if ($status) { $w .= " AND t.status=?"; $p[] = $status; }
            $stmt = $db->prepare("
                SELECT t.task_title,
                       p.project_name,
                       CONCAT(e.first_name,' ',e.last_name) AS assigned_to,
                       t.priority, t.due_date, t.status
                FROM tasks t
                LEFT JOIN projects p  ON t.project_id=p.id
                LEFT JOIN employees e ON t.assigned_to=e.id
                $w ORDER BY t.due_date DESC");
            $stmt->execute($p);
            return $stmt->fetchAll();

        case 'timesheet_summary':
            $stmt = $db->prepare("
                SELECT CONCAT(e.first_name,' ',e.last_name) AS full_name,
                       p.project_name,
                       SUM(ts.hours_logged) AS total_hours,
                       ts.work_date
                FROM timesheets ts
                JOIN employees e ON ts.employee_id=e.id
                LEFT JOIN projects p ON ts.project_id=p.id
                WHERE ts.work_date BETWEEN ? AND ?
                GROUP BY e.id, ts.work_date ORDER BY ts.work_date DESC");
            $stmt->execute([$from, $to]);
            return $stmt->fetchAll();

        case 'leads_conversion':
            $w = "WHERE l.created_at BETWEEN ? AND ?"; $p = [$from, $to];
            if ($status) { $w .= " AND l.status=?"; $p[] = $status; }
            $stmt = $db->prepare("
                SELECT l.company_name, l.contact_person, l.source, l.status,
                       CONCAT(e.first_name,' ',e.last_name) AS assigned_to,
                       DATE(l.created_at) AS created_at
                FROM leads l
                LEFT JOIN employees e ON l.assigned_to=e.id
                $w ORDER BY l.created_at DESC");
            $stmt->execute($p);
            return $stmt->fetchAll();

        case 'sales_pipeline':
            $stmt = $db->prepare("
                SELECT d.deal_title,
                       c.company_name AS client_name,
                       d.deal_value, d.stage, d.probability,
                       d.expected_closing_date AS expected_close
                FROM deals d
                LEFT JOIN clients c ON d.client_id=c.id
                WHERE d.created_at BETWEEN ? AND ?
                ORDER BY d.deal_value DESC");
            $stmt->execute([$from, $to]);
            return $stmt->fetchAll();

        case 'client_growth':
            $stmt = $db->prepare("
                SELECT c.company_name, c.industry, c.country,
                       COUNT(DISTINCT i.id) AS total_invoices,
                       COALESCE(SUM(i.paid_amount),0) AS total_revenue,
                       DATE(c.created_at) AS created_at
                FROM clients c
                LEFT JOIN invoices i ON i.client_id=c.id
                WHERE c.created_at BETWEEN ? AND ?
                GROUP BY c.id ORDER BY total_revenue DESC");
            $stmt->execute([$from, $to]);
            return $stmt->fetchAll();

        case 'revenue_summary':
            $stmt = $db->prepare("
                SELECT DATE_FORMAT(invoice_date,'%Y-%m') AS month,
                       COUNT(*) AS invoices,
                       SUM(total_amount) AS total_invoiced,
                       SUM(paid_amount)  AS total_paid,
                       SUM(total_amount-paid_amount) AS outstanding
                FROM invoices
                WHERE invoice_date BETWEEN ? AND ?
                GROUP BY month ORDER BY month DESC");
            $stmt->execute([$from, $to]);
            return $stmt->fetchAll();

        case 'expense_summary':
            $w = "WHERE ex.expense_date BETWEEN ? AND ?"; $p = [$from, $to];
            if ($status) { $w .= " AND ex.status=?"; $p[] = $status; }
            $stmt = $db->prepare("
                SELECT ec.name AS category,
                       u.name AS employee,
                       ex.amount, ex.expense_date, ex.status
                FROM expenses ex
                LEFT JOIN expense_categories ec ON ex.category_id=ec.id
                LEFT JOIN users u ON ex.created_by=u.id
                $w ORDER BY ex.expense_date DESC");
            $stmt->execute($p);
            return $stmt->fetchAll();

        case 'profit_overview':
            $stmt = $db->prepare("
                SELECT inv.month,
                       COALESCE(inv.revenue,0) AS revenue,
                       COALESCE(exp.expenses,0) AS expenses,
                       COALESCE(inv.revenue,0)-COALESCE(exp.expenses,0) AS profit,
                       CASE WHEN COALESCE(inv.revenue,0)>0
                            THEN ROUND((COALESCE(inv.revenue,0)-COALESCE(exp.expenses,0))/COALESCE(inv.revenue,0)*100,1)
                            ELSE 0 END AS margin_pct
                FROM (
                    SELECT DATE_FORMAT(invoice_date,'%Y-%m') AS month, SUM(paid_amount) AS revenue
                    FROM invoices WHERE invoice_date BETWEEN ? AND ? GROUP BY month
                ) inv
                LEFT JOIN (
                    SELECT DATE_FORMAT(expense_date,'%Y-%m') AS month, SUM(amount) AS expenses
                    FROM expenses WHERE expense_date BETWEEN ? AND ? AND status='approved' GROUP BY month
                ) exp ON inv.month=exp.month
                ORDER BY inv.month DESC");
            $stmt->execute([$from, $to, $from, $to]);
            return $stmt->fetchAll();

        case 'invoice_aging':
            $w = "WHERE 1=1"; $p = [];
            if ($status) { $w .= " AND i.status=?"; $p[] = $status; }
            $stmt = $db->prepare("
                SELECT i.invoice_number,
                       c.company_name AS client_name,
                       i.total_amount, i.paid_amount, i.balance_amount,
                       i.due_date,
                       GREATEST(0, DATEDIFF(CURDATE(), i.due_date)) AS days_overdue,
                       i.status
                FROM invoices i
                LEFT JOIN clients c ON i.client_id=c.id
                $w ORDER BY days_overdue DESC");
            $stmt->execute($p);
            return $stmt->fetchAll();

        case 'asset_allocation':
            $stmt = $db->prepare("
                SELECT a.asset_name, a.asset_code AS asset_tag,
                       ac.category_name AS category,
                       CONCAT(e.first_name,' ',e.last_name) AS assigned_to,
                       aa.assigned_date, a.status
                FROM assets a
                LEFT JOIN asset_categories ac ON a.category_id=ac.id
                LEFT JOIN asset_assignments aa ON aa.asset_id=a.id AND aa.actual_return_date IS NULL
                LEFT JOIN employees e ON aa.employee_id=e.id
                ORDER BY a.asset_name");
            $stmt->execute();
            return $stmt->fetchAll();

        case 'maintenance_summary':
            $stmt = $db->prepare("
                SELECT a.asset_name,
                       m.maintenance_type, m.maintenance_date, m.cost, m.status
                FROM asset_maintenance m
                JOIN assets a ON m.asset_id=a.id
                WHERE m.maintenance_date BETWEEN ? AND ?
                ORDER BY m.maintenance_date DESC");
            $stmt->execute([$from, $to]);
            return $stmt->fetchAll();

        case 'license_expiry':
            $stmt = $db->prepare("
                SELECT sl.software_name, sl.license_key, sl.seats,
                       sl.expiry_date,
                       DATEDIFF(sl.expiry_date, CURDATE()) AS days_left,
                       CASE WHEN sl.expiry_date < CURDATE() THEN 'Expired'
                            WHEN DATEDIFF(sl.expiry_date, CURDATE()) <= 30 THEN 'Expiring Soon'
                            ELSE 'Active' END AS status
                FROM software_licenses sl
                ORDER BY sl.expiry_date ASC");
            $stmt->execute();
            return $stmt->fetchAll();

        case 'ticket_resolution':
            $w = "WHERE t.created_at BETWEEN ? AND ?"; $p = [$from, $to];
            if ($status) { $w .= " AND t.status=?"; $p[] = $status; }
            $stmt = $db->prepare("
                SELECT t.ticket_number, t.subject,
                       CONCAT(req.first_name,' ',req.last_name) AS requester,
                       CONCAT(ag.first_name,' ',ag.last_name)   AS assigned_to,
                       t.priority, t.status,
                       DATE(t.created_at) AS created_at,
                       DATE(t.resolved_at) AS resolved_at
                FROM tickets t
                LEFT JOIN employees req ON t.employee_id=req.id
                LEFT JOIN employees ag  ON t.assigned_to=ag.id
                $w ORDER BY t.created_at DESC");
            $stmt->execute($p);
            return $stmt->fetchAll();

        case 'sla_performance':
            $stmt = $db->prepare("
                SELECT t.ticket_number, t.subject,
                       s.name AS sla_name, t.priority,
                       ROUND(TIMESTAMPDIFF(MINUTE, t.created_at, t.first_response_at)/60,1) AS response_time,
                       ROUND(TIMESTAMPDIFF(MINUTE, t.created_at, t.resolved_at)/60,1) AS resolution_time,
                       CASE WHEN t.resolved_at IS NULL THEN 'Open'
                            WHEN TIMESTAMPDIFF(HOUR, t.created_at, t.resolved_at) <= s.resolution_time THEN 'Met'
                            ELSE 'Breached' END AS sla_status
                FROM tickets t
                LEFT JOIN slas s ON t.sla_id=s.id
                WHERE t.created_at BETWEEN ? AND ?
                ORDER BY t.created_at DESC");
            $stmt->execute([$from, $to]);
            return $stmt->fetchAll();

        case 'support_workload':
            $stmt = $db->prepare("
                SELECT CONCAT(e.first_name,' ',e.last_name) AS agent_name,
                       SUM(t.status IN ('open','in_progress')) AS open_tickets,
                       SUM(t.status='resolved') AS resolved,
                       COUNT(*) AS total,
                       ROUND(AVG(CASE WHEN t.resolved_at IS NOT NULL
                                      THEN TIMESTAMPDIFF(HOUR, t.created_at, t.resolved_at)
                                      ELSE NULL END),1) AS avg_resolution
                FROM tickets t
                JOIN employees e ON t.assigned_to=e.id
                WHERE t.created_at BETWEEN ? AND ?
                GROUP BY e.id ORDER BY total DESC");
            $stmt->execute([$from, $to]);
            return $stmt->fetchAll();

        default:
            return [];
    }
}

function getReportSummary(string $type, array $data): array {
    if (empty($data)) return [];

    switch ($type) {
        case 'employee_summary':
            $active = count(array_filter($data, fn($r) => $r['status'] === 'active'));
            return [
                ['label'=>'Total Employees', 'value'=> count($data)],
                ['label'=>'Active',          'value'=> $active],
                ['label'=>'Inactive',        'value'=> count($data) - $active],
            ];
        case 'attendance_report':
            $avg = count($data) ? round(array_sum(array_column($data,'attendance_pct'))/count($data),1) : 0;
            return [
                ['label'=>'Employees',        'value'=> count($data)],
                ['label'=>'Avg Attendance %', 'value'=> $avg . '%'],
                ['label'=>'Total Present',    'value'=> array_sum(array_column($data,'present'))],
                ['label'=>'Total Absent',     'value'=> array_sum(array_column($data,'absent'))],
            ];
        case 'revenue_summary':
            return [
                ['label'=>'Total Invoiced', 'value'=> formatMoney(array_sum(array_column($data,'total_invoiced')))],
                ['label'=>'Total Paid',     'value'=> formatMoney(array_sum(array_column($data,'total_paid')))],
                ['label'=>'Outstanding',    'value'=> formatMoney(array_sum(array_column($data,'outstanding')))],
                ['label'=>'Invoices',       'value'=> array_sum(array_column($data,'invoices'))],
            ];
        case 'expense_summary':
            return [
                ['label'=>'Total Expenses', 'value'=> formatMoney(array_sum(array_column($data,'amount')))],
                ['label'=>'Records',        'value'=> count($data)],
            ];
        case 'profit_overview':
            return [
                ['label'=>'Total Revenue',  'value'=> formatMoney(array_sum(array_column($data,'revenue')))],
                ['label'=>'Total Expenses', 'value'=> formatMoney(array_sum(array_column($data,'expenses')))],
                ['label'=>'Net Profit',     'value'=> formatMoney(array_sum(array_column($data,'profit')))],
            ];
        case 'invoice_aging':
            $overdue = count(array_filter($data, fn($r) => $r['days_overdue'] > 0));
            return [
                ['label'=>'Total Invoices', 'value'=> count($data)],
                ['label'=>'Overdue',        'value'=> $overdue],
                ['label'=>'Total Balance',  'value'=> formatMoney(array_sum(array_column($data,'balance_amount')))],
            ];
        case 'ticket_resolution':
            $resolved = count(array_filter($data, fn($r) => $r['status'] === 'resolved'));
            return [
                ['label'=>'Total Tickets', 'value'=> count($data)],
                ['label'=>'Resolved',      'value'=> $resolved],
                ['label'=>'Open',          'value'=> count($data) - $resolved],
            ];
        default:
            return [['label'=>'Total Records', 'value'=> count($data)]];
    }
}
