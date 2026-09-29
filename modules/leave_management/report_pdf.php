<?php
/**
 * Leave Report PDF Download
 * Generates and downloads the current report as a PDF using Dompdf.
 */
session_start();
$basePath = dirname(__DIR__, 2);
require_once $basePath . '/config/database.php';
require_once $basePath . '/helpers/security.php';
require_once $basePath . '/helpers/rbac.php';
require_once $basePath . '/helpers/redirect.php';

require_once __DIR__ . '/../../vendor/autoload.php';
require_once $basePath . '/helpers/template_renderer.php';

if (!hasPermission('leave_management.report') && !hasPermission('leaves.view')) {
    die('Access Denied');
}

$db = getDB();

$reportType = $_GET['report_type'] ?? 'employee';
$employeeId = $_GET['employee_id'] ?? '';
$departmentId = $_GET['department_id'] ?? '';
$leaveTypeId = $_GET['leave_type_id'] ?? '';
$status = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? date('Y-01-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);
$dompdf = new Dompdf($options);

// ---------- Build Report HTML ----------
$html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
$html .= '<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; }
    h1 { font-size: 18px; margin-bottom: 4px; }
    .subtitle { color: #666; font-size: 11px; margin-bottom: 16px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    th { background: #f3f4f6; text-align: left; padding: 6px 8px; font-size: 9px; text-transform: uppercase; color: #666; border: 1px solid #e5e7eb; }
    td { padding: 6px 8px; border: 1px solid #e5e7eb; font-size: 11px; }
    tr:nth-child(even) { background: #f9fafb; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-red { color: #dc2626; font-weight: bold; }
    .footer { margin-top: 24px; font-size: 9px; color: #999; text-align: center; }
    .badge { display: inline-block; padding: 1px 6px; font-size: 9px; border-radius: 3px; }
    .badge-yellow { background: #fef3c7; color: #92400e; }
    .badge-green { background: #d1fae5; color: #065f46; }
    .badge-red { background: #fee2e2; color: #991b1b; }
    .badge-gray { background: #f3f4f6; color: #6b7280; }
</style></head><body>';

$title = 'Leave Report';
$subtitle = date('M d, Y', strtotime($dateFrom)) . ' — ' . date('M d, Y', strtotime($dateTo));

if ($reportType === 'employee') {
    $title = 'Leave Summary by Employee';
    $sql = "SELECT e.id as emp_id, e.first_name, e.last_name, e.employee_code,
                   d.department_name,
                   COUNT(lr.id) as total_leaves,
                   COALESCE(SUM(lr.total_days), 0) as total_days_used,
                   COALESCE(SUM(CASE WHEN lr.is_lop = 1 THEN lr.total_days ELSE 0 END), 0) as lop_days
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN leave_requests lr ON e.id = lr.employee_id
                AND lr.status = 'approved'
                AND lr.from_date >= " . $db->quote($dateFrom) . "
                AND lr.from_date <= " . $db->quote($dateTo) . "
            WHERE e.status = 'active'";
    if ($employeeId) { $sql .= " AND e.id = " . (int)$employeeId; }
    $sql .= " GROUP BY e.id ORDER BY e.first_name";
    $rows = $db->query($sql)->fetchAll();

    $html .= '<h1>' . $title . '</h1><div class="subtitle">' . $subtitle . '</div>';
    $html .= '<table><thead><tr>
        <th>Employee</th><th>Department</th><th class="text-center">Leaves</th>
        <th class="text-center">Days Used</th><th class="text-center">LOP</th>
    </tr></thead><tbody>';
    foreach ($rows as $r) {
        $lopClass = $r['lop_days'] > 0 ? 'text-red' : '';
        $html .= '<tr>
            <td>' . htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) . '<br><span style="font-size:9px;color:#999;">' . htmlspecialchars($r['employee_code']) . '</span></td>
            <td>' . htmlspecialchars($r['department_name'] ?? '-') . '</td>
            <td class="text-center">' . $r['total_leaves'] . '</td>
            <td class="text-center">' . $r['total_days_used'] . '</td>
            <td class="text-center ' . $lopClass . '">' . $r['lop_days'] . '</td>
        </tr>';
    }
    if (empty($rows)) $html .= '<tr><td colspan="5" class="text-center" style="color:#999;">No data found</td></tr>';
    $html .= '</tbody></table>';

} elseif ($reportType === 'department') {
    $title = 'Leave Summary by Department';
    $sql = "SELECT d.department_name, COUNT(DISTINCT e.id) as emp_count,
                   COUNT(lr.id) as total_leaves, COALESCE(SUM(lr.total_days), 0) as total_days_used
            FROM departments d
            LEFT JOIN employees e ON e.department_id = d.id AND e.status = 'active'
            LEFT JOIN leave_requests lr ON e.id = lr.employee_id
                AND lr.status = 'approved'
                AND lr.from_date >= " . $db->quote($dateFrom) . "
                AND lr.from_date <= " . $db->quote($dateTo) . "
            WHERE d.status = 'active'";
    if ($departmentId) { $sql .= " AND d.id = " . (int)$departmentId; }
    $sql .= " GROUP BY d.id ORDER BY d.department_name";
    $rows = $db->query($sql)->fetchAll();

    $html .= '<h1>' . $title . '</h1><div class="subtitle">' . $subtitle . '</div>';
    $html .= '<table><thead><tr>
        <th>Department</th><th class="text-center">Employees</th><th class="text-center">Leaves</th>
        <th class="text-center">Days Used</th><th class="text-center">Avg / Emp</th>
    </tr></thead><tbody>';
    foreach ($rows as $r) {
        $avg = $r['emp_count'] > 0 ? round($r['total_days_used'] / $r['emp_count'], 1) : 0;
        $html .= '<tr>
            <td>' . htmlspecialchars($r['department_name']) . '</td>
            <td class="text-center">' . $r['emp_count'] . '</td>
            <td class="text-center">' . $r['total_leaves'] . '</td>
            <td class="text-center">' . $r['total_days_used'] . '</td>
            <td class="text-center">' . $avg . '</td>
        </tr>';
    }
    if (empty($rows)) $html .= '<tr><td colspan="5" class="text-center" style="color:#999;">No data found</td></tr>';
    $html .= '</tbody></table>';

} elseif ($reportType === 'type') {
    $title = 'Leave Summary by Type';
    $sql = "SELECT lt.leave_name, lt.leave_code, lt.total_days as default_days,
                   COUNT(lr.id) as times_used, COALESCE(SUM(lr.total_days), 0) as total_days_used,
                   COALESCE(SUM(CASE WHEN lr.is_lop = 1 THEN lr.total_days ELSE 0 END), 0) as lop_days
            FROM leave_types lt
            LEFT JOIN leave_requests lr ON lt.id = lr.leave_type_id
                AND lr.status = 'approved'
                AND lr.from_date >= " . $db->quote($dateFrom) . "
                AND lr.from_date <= " . $db->quote($dateTo) . "
            WHERE lt.status = 'active'";
    if ($leaveTypeId) { $sql .= " AND lt.id = " . (int)$leaveTypeId; }
    $sql .= " GROUP BY lt.id ORDER BY lt.leave_name";
    $rows = $db->query($sql)->fetchAll();

    $html .= '<h1>' . $title . '</h1><div class="subtitle">' . $subtitle . '</div>';
    $html .= '<table><thead><tr>
        <th>Leave Type</th><th class="text-center">Code</th><th class="text-center">Default</th>
        <th class="text-center">Times Used</th><th class="text-center">Days Used</th><th class="text-center">LOP</th>
    </tr></thead><tbody>';
    foreach ($rows as $r) {
        $lopClass = $r['lop_days'] > 0 ? 'text-red' : '';
        $html .= '<tr>
            <td>' . htmlspecialchars($r['leave_name']) . '</td>
            <td class="text-center">' . htmlspecialchars($r['leave_code']) . '</td>
            <td class="text-center">' . $r['default_days'] . '</td>
            <td class="text-center">' . $r['times_used'] . '</td>
            <td class="text-center">' . $r['total_days_used'] . '</td>
            <td class="text-center ' . $lopClass . '">' . $r['lop_days'] . '</td>
        </tr>';
    }
    if (empty($rows)) $html .= '<tr><td colspan="6" class="text-center" style="color:#999;">No data found</td></tr>';
    $html .= '</tbody></table>';

} else {
    // Detailed Log
    $title = 'Detailed Leave Log';
    $detailWhere = "WHERE 1=1";
    $detailParams = [];
    if ($employeeId) { $detailWhere .= " AND lr.employee_id = ?"; $detailParams[] = $employeeId; }
    if ($status) { $detailWhere .= " AND lr.status = ?"; $detailParams[] = $status; }
    $detailWhere .= " AND lr.from_date >= ? AND lr.from_date <= ?";
    $detailParams[] = $dateFrom;
    $detailParams[] = $dateTo;

    $stmt = $db->prepare("
        SELECT lr.*, e.first_name, e.last_name, e.employee_code, lt.leave_name, lt.leave_code,
               u.name as approver_name
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.id
        JOIN leave_types lt ON lr.leave_type_id = lt.id
        LEFT JOIN users u ON lr.approved_by = u.id
        $detailWhere
        ORDER BY lr.created_at DESC
    ");
    $stmt->execute($detailParams);
    $rows = $stmt->fetchAll();

    $statusBadge = [
        'pending' => 'badge-yellow', 'approved' => 'badge-green',
        'rejected' => 'badge-red', 'cancelled' => 'badge-gray',
    ];

    $html .= '<h1>' . $title . '</h1><div class="subtitle">' . $subtitle . '</div>';
    $html .= '<table><thead><tr>
        <th>Employee</th><th>Type</th><th>Dates</th><th class="text-center">Days</th>
        <th>Reason</th><th class="text-center">LOP</th><th class="text-center">Status</th><th>Approved By</th>
    </tr></thead><tbody>';
    foreach ($rows as $r) {
        $html .= '<tr>
            <td>' . htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) . '</td>
            <td>' . htmlspecialchars($r['leave_name']) . ' (' . $r['leave_code'] . ')</td>
            <td>' . date('M d', strtotime($r['from_date'])) . ' - ' . date('M d, Y', strtotime($r['to_date'])) . '</td>
            <td class="text-center">' . $r['total_days'] . '</td>
            <td>' . htmlspecialchars($r['reason'] ?? '-') . '</td>
            <td class="text-center">' . ($r['is_lop'] ? '<span class="text-red">LOP</span>' : '-') . '</td>
            <td class="text-center"><span class="badge ' . ($statusBadge[$r['status']] ?? 'badge-gray') . '">' . ucfirst($r['status']) . '</span></td>
            <td>' . htmlspecialchars($r['approver_name'] ?? '-') . '</td>
        </tr>';
    }
    if (empty($rows)) $html .= '<tr><td colspan="8" class="text-center" style="color:#999;">No leave records found</td></tr>';
    $html .= '</tbody></table>';
}

$html .= '<div class="footer">Generated: ' . date('M d, Y h:i A') . ' | ERP Leave Management</div>';
$html .= '</body></html>';

// ---------- Generate PDF ----------
// Try template system first
$templateData = [
    'report_title'      => $title,
    'leave_report_type' => $reportType,
    'date_from'         => $dateFrom,
    'date_to'           => $dateTo,
    'generated_by'      => $_SESSION['user_name'] ?? 'User',
    'generated_date'    => date('Y-m-d H:i:s'),
    'report_type_label' => $reportType,
    'report_data'       => $rows ?? [],
];

$filename = 'leave_report_' . $reportType . '_' . date('Ymd');

if (hasTemplateForModule('leave_management', $reportType)) {
    streamTemplateForModule('leave_management', $reportType, $templateData, $filename);
    // streamTemplateForModule calls exit()
}

// Fallback: existing Dompdf generation
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$filename = strtolower(str_replace(' ', '_', $title)) . '_' . date('Ymd') . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
