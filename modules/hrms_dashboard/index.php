<?php
$pageTitle = 'HRMS Dashboard';
$dashboardKey = 'hrms';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('employees.view') && !hasPermission('departments.view') && !hasPermission('attendance.view')) { die('Access Denied'); }

$db = getDB();

$totalEmployees = $db->query("SELECT COUNT(*) FROM employees")->fetchColumn();
$activeEmployees = $db->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn();
$inactiveEmployees = $db->query("SELECT COUNT(*) FROM employees WHERE status != 'active'")->fetchColumn();
$totalDepartments = $db->query("SELECT COUNT(*) FROM departments WHERE status = 'active'")->fetchColumn();
$totalDesignations = $db->query("SELECT COUNT(*) FROM designations WHERE status = 'active'")->fetchColumn();
$presentToday = $db->query("SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE() AND status = 'present'")->fetchColumn();
$absentToday = $db->query("SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE() AND status = 'absent'")->fetchColumn();
$onLeaveToday = $db->query("SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE() AND status = 'leave'")->fetchColumn();
$pendingLeaves = $db->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'")->fetchColumn();
$approvedLeaves = $db->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'approved' AND MONTH(from_date) = MONTH(NOW()) AND YEAR(from_date) = YEAR(NOW())")->fetchColumn();
$upcomingHolidays = $db->query("SELECT COUNT(*) FROM holidays WHERE holiday_date >= CURDATE()")->fetchColumn();
$newHiresThisMonth = $db->query("SELECT COUNT(*) FROM employees WHERE MONTH(joining_date) = MONTH(NOW()) AND YEAR(joining_date) = YEAR(NOW())")->fetchColumn();
$attendanceRate = $activeEmployees > 0 ? round(($presentToday / $activeEmployees) * 100, 1) : 0;

$employeesByDept = $db->query("
    SELECT d.department_name, COUNT(e.id) as cnt
    FROM departments d
    LEFT JOIN employees e ON d.id = e.department_id AND e.status = 'active'
    WHERE d.status = 'active'
    GROUP BY d.id
    ORDER BY cnt DESC
")->fetchAll();

$employeesByType = $db->query("
    SELECT employment_type, COUNT(*) as cnt
    FROM employees WHERE status = 'active'
    GROUP BY employment_type
    ORDER BY cnt DESC
")->fetchAll();

$employeesByGender = $db->query("
    SELECT gender, COUNT(*) as cnt
    FROM employees WHERE status = 'active'
    GROUP BY gender
")->fetchAll();

$attendanceByStatus = $db->query("
    SELECT status, COUNT(*) as cnt
    FROM attendance WHERE attendance_date = CURDATE()
    GROUP BY status
")->fetchAll();

$leaveByType = $db->query("
    SELECT lt.leave_name, COUNT(lr.id) as cnt
    FROM leave_requests lr
    JOIN leave_types lt ON lr.leave_type_id = lt.id
    WHERE lr.status = 'approved' AND MONTH(lr.from_date) = MONTH(NOW()) AND YEAR(lr.from_date) = YEAR(NOW())
    GROUP BY lt.id
    ORDER BY cnt DESC
")->fetchAll();

$monthlyHires = $db->query("
    SELECT DATE_FORMAT(joining_date, '%b') as label, COUNT(*) as cnt
    FROM employees
    WHERE joining_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(joining_date, '%Y-%m')
    ORDER BY MIN(joining_date)
")->fetchAll();

$recentEmployees = $db->query("
    SELECT e.*, d.department_name, des.designation_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN designations des ON e.designation_id = des.id
    ORDER BY e.created_at DESC LIMIT 8
")->fetchAll();

$pendingLeaveRequests = $db->query("
    SELECT lr.*, e.first_name, e.last_name, lt.leave_name
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id = e.id
    JOIN leave_types lt ON lr.leave_type_id = lt.id
    WHERE lr.status = 'pending'
    ORDER BY lr.from_date ASC LIMIT 6
")->fetchAll();

$upcomingHolidayList = $db->query("
    SELECT * FROM holidays
    WHERE holiday_date >= CURDATE()
    ORDER BY holiday_date ASC LIMIT 5
")->fetchAll();

$empStatusColors = [
    'active' => 'bg-green-100 text-green-800',
    'inactive' => 'bg-gray-100 text-gray-800',
    'terminated' => 'bg-red-100 text-red-800',
    'resigned' => 'bg-orange-100 text-orange-800'
];
$leaveStatusColors = [
    'pending' => 'bg-yellow-100 text-yellow-800',
    'approved' => 'bg-green-100 text-green-800',
    'rejected' => 'bg-red-100 text-red-800',
    'cancelled' => 'bg-gray-100 text-gray-800'
];
$genderColors = ['male' => '#6366f1', 'female' => '#ec4899', 'other' => '#14b8a6'];
$typeLabels = ['full_time' => 'Full Time', 'part_time' => 'Part Time', 'contract' => 'Contract', 'intern' => 'Intern'];
?>

<div id="dashboard-old-content">

<!-- Header -->
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">HRMS Dashboard</h1>
    <p class="text-gray-500 text-sm mt-1">Human Resource Management Overview</p>
</div>

<!-- ===== KPI Stats Cards ===== -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
    <a href="<?= url('modules/employees/index.php') ?>" class="bg-white rounded-xl shadow-sm p-4 border border-slate-100 hover:shadow-md transition block">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Employees</span>
            <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center">
                <i class="fas fa-user-tie text-blue-500 text-sm"></i>
            </div>
        </div>
        <p class="text-2xl font-bold text-slate-800"><?= $activeEmployees ?></p>
        <p class="text-xs text-slate-400 mt-1"><?= $newHiresThisMonth ?> hired this month</p>
    </a>
    <a href="<?= url('modules/departments/index.php') ?>" class="bg-white rounded-xl shadow-sm p-4 border border-slate-100 hover:shadow-md transition block">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Departments</span>
            <div class="w-9 h-9 rounded-lg bg-violet-50 flex items-center justify-center">
                <i class="fas fa-building text-violet-500 text-sm"></i>
            </div>
        </div>
        <p class="text-2xl font-bold text-slate-800"><?= $totalDepartments ?></p>
    </a>
    <a href="<?= url('modules/attendance/index.php') ?>" class="bg-white rounded-xl shadow-sm p-4 border border-slate-100 hover:shadow-md transition block">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Present Today</span>
            <div class="w-9 h-9 rounded-lg bg-green-50 flex items-center justify-center">
                <i class="fas fa-check-circle text-green-500 text-sm"></i>
            </div>
        </div>
        <p class="text-2xl font-bold text-green-600"><?= $presentToday ?></p>
        <p class="text-xs text-slate-400 mt-1"><?= $attendanceRate ?>% attendance rate</p>
    </a>
    <a href="<?= url('modules/leave_requests/index.php') ?>" class="bg-white rounded-xl shadow-sm p-4 border border-slate-100 hover:shadow-md transition block">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">On Leave</span>
            <div class="w-9 h-9 rounded-lg bg-amber-50 flex items-center justify-center">
                <i class="fas fa-calendar-minus text-amber-500 text-sm"></i>
            </div>
        </div>
        <p class="text-2xl font-bold text-amber-600"><?= $onLeaveToday ?></p>
        <p class="text-xs text-slate-400 mt-1"><?= $pendingLeaves ?> pending requests</p>
    </a>
    <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-100">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Absent Today</span>
            <div class="w-9 h-9 rounded-lg bg-red-50 flex items-center justify-center">
                <i class="fas fa-times-circle text-red-500 text-sm"></i>
            </div>
        </div>
        <p class="text-2xl font-bold text-red-600"><?= $absentToday ?></p>
    </div>
    <a href="<?= url('modules/holidays/index.php') ?>" class="bg-white rounded-xl shadow-sm p-4 border border-slate-100 hover:shadow-md transition block">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Holidays</span>
            <div class="w-9 h-9 rounded-lg bg-cyan-50 flex items-center justify-center">
                <i class="fas fa-umbrella-beach text-cyan-500 text-sm"></i>
            </div>
        </div>
        <p class="text-2xl font-bold text-slate-800"><?= $upcomingHolidays ?></p>
        <p class="text-xs text-slate-400 mt-1">upcoming</p>
    </a>
</div>

<!-- ===== Sub-Module Navigation Cards ===== -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
    <?php if (hasPermission('employees.view')): ?>
    <a href="<?= url('modules/employees/index.php') ?>" class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 hover:shadow-md transition group">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-lg bg-blue-500 flex items-center justify-center group-hover:scale-110 transition">
                <i class="fas fa-user-tie text-white"></i>
            </div>
            <div>
                <h3 class="font-semibold text-slate-800 text-sm">Employees</h3>
                <p class="text-xs text-slate-400">Manage staff</p>
            </div>
        </div>
        <span class="text-xl font-bold text-slate-800"><?= $activeEmployees ?></span>
    </a>
    <?php endif; ?>
    <?php if (hasPermission('departments.view')): ?>
    <a href="<?= url('modules/departments/index.php') ?>" class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 hover:shadow-md transition group">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-lg bg-violet-500 flex items-center justify-center group-hover:scale-110 transition">
                <i class="fas fa-building text-white"></i>
            </div>
            <div>
                <h3 class="font-semibold text-slate-800 text-sm">Departments</h3>
                <p class="text-xs text-slate-400">Org structure</p>
            </div>
        </div>
        <span class="text-xl font-bold text-slate-800"><?= $totalDepartments ?></span>
    </a>
    <?php endif; ?>
    <?php if (hasPermission('designations.view')): ?>
    <a href="<?= url('modules/designations/index.php') ?>" class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 hover:shadow-md transition group">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-lg bg-indigo-500 flex items-center justify-center group-hover:scale-110 transition">
                <i class="fas fa-briefcase text-white"></i>
            </div>
            <div>
                <h3 class="font-semibold text-slate-800 text-sm">Designations</h3>
                <p class="text-xs text-slate-400">Job roles</p>
            </div>
        </div>
        <span class="text-xl font-bold text-slate-800"><?= $totalDesignations ?></span>
    </a>
    <?php endif; ?>
    <?php if (hasPermission('attendance.view')): ?>
    <a href="<?= url('modules/attendance/index.php') ?>" class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 hover:shadow-md transition group">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-lg bg-green-500 flex items-center justify-center group-hover:scale-110 transition">
                <i class="fas fa-clock text-white"></i>
            </div>
            <div>
                <h3 class="font-semibold text-slate-800 text-sm">Attendance</h3>
                <p class="text-xs text-slate-400">Daily tracking</p>
            </div>
        </div>
        <span class="text-xl font-bold text-green-600"><?= $presentToday ?></span>
    </a>
    <?php endif; ?>
    <?php if (hasPermission('leaves.view')): ?>
    <a href="<?= url('modules/leave_requests/index.php') ?>" class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 hover:shadow-md transition group">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-lg bg-amber-500 flex items-center justify-center group-hover:scale-110 transition">
                <i class="fas fa-calendar-minus text-white"></i>
            </div>
            <div>
                <h3 class="font-semibold text-slate-800 text-sm">Leave Requests</h3>
                <p class="text-xs text-slate-400">Time off</p>
            </div>
        </div>
        <span class="text-xl font-bold text-amber-600"><?= $pendingLeaves ?></span>
    </a>
    <?php endif; ?>
    <?php if (hasPermission('holidays.view')): ?>
    <a href="<?= url('modules/holidays/index.php') ?>" class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 hover:shadow-md transition group">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-lg bg-cyan-500 flex items-center justify-center group-hover:scale-110 transition">
                <i class="fas fa-umbrella-beach text-white"></i>
            </div>
            <div>
                <h3 class="font-semibold text-slate-800 text-sm">Holidays</h3>
                <p class="text-xs text-slate-400">Calendar</p>
            </div>
        </div>
        <span class="text-xl font-bold text-slate-800"><?= $upcomingHolidays ?></span>
    </a>
    <?php endif; ?>
</div>

<!-- ===== Charts Row 1: Employees by Department + Attendance Today ===== -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-chart-bar text-blue-500 text-xs"></i>
            Employees by Department
        </h3>
        <div id="empByDeptChart" style="min-height: 260px"></div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-chart-pie text-green-500 text-xs"></i>
            Attendance Today
        </h3>
        <div id="attendanceChart" style="min-height: 260px"></div>
    </div>
</div>

<!-- ===== Charts Row 2: Leave by Type + Monthly Hires ===== -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-chart-bar text-amber-500 text-xs"></i>
            Approved Leaves by Type (This Month)
        </h3>
        <div id="leaveByTypeChart" style="min-height: 260px"></div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-chart-line text-violet-500 text-xs"></i>
            New Hires (6 Months)
        </h3>
        <div id="monthlyHiresChart" style="min-height: 260px"></div>
    </div>
</div>

<!-- ===== Charts Row 3: Gender Distribution + Employment Type ===== -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-venus-mars text-pink-500 text-xs"></i>
            Gender Distribution
        </h3>
        <div id="genderChart" style="min-height: 220px"></div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-users text-indigo-500 text-xs"></i>
            Employment Type
        </h3>
        <div id="empTypeChart" style="min-height: 220px"></div>
    </div>
</div>

<!-- ===== Pending Leave Requests + Upcoming Holidays ===== -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-calendar-alt text-amber-500 text-xs"></i>
            Pending Leave Requests
        </h3>
        <?php if (empty($pendingLeaveRequests)): ?>
        <p class="text-sm text-slate-400 text-center py-6">No pending requests</p>
        <?php else: ?>
        <div class="space-y-0">
            <?php foreach ($pendingLeaveRequests as $pl): ?>
            <div class="flex items-center justify-between py-2.5 border-b border-slate-50 last:border-0">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-slate-800"><?= htmlspecialchars($pl['first_name'] . ' ' . $pl['last_name']) ?></p>
                    <p class="text-xs text-slate-400"><?= htmlspecialchars($pl['leave_name']) ?> &middot; <?= date('M d', strtotime($pl['from_date'])) ?> - <?= date('M d', strtotime($pl['to_date'])) ?></p>
                </div>
                <div class="flex items-center gap-2 ml-3">
                    <span class="text-xs text-slate-500"><?= $pl['total_days'] ?>d</span>
                    <span class="px-2 py-0.5 text-xs rounded-full <?= $leaveStatusColors[$pl['status']] ?? '' ?>">Pending</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <i class="fas fa-umbrella-beach text-cyan-500 text-xs"></i>
            Upcoming Holidays
        </h3>
        <?php if (empty($upcomingHolidayList)): ?>
        <p class="text-sm text-slate-400 text-center py-6">No upcoming holidays</p>
        <?php else: ?>
        <div class="space-y-0">
            <?php foreach ($upcomingHolidayList as $h): ?>
            <div class="flex items-center gap-3 py-2.5 border-b border-slate-50 last:border-0">
                <div class="w-10 h-10 rounded-lg bg-cyan-50 flex items-center justify-center flex-shrink-0">
                    <div class="text-center">
                        <div class="text-xs font-bold text-cyan-600"><?= date('M', strtotime($h['holiday_date'])) ?></div>
                        <div class="text-sm font-bold text-slate-800 leading-none"><?= date('d', strtotime($h['holiday_date'])) ?></div>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-slate-800"><?= htmlspecialchars($h['holiday_name']) ?></p>
                    <p class="text-xs text-slate-400"><?= ucfirst($h['holiday_type']) ?></p>
                </div>
                <span class="text-xs text-slate-500"><?= date('l', strtotime($h['holiday_date'])) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== Recent Employees ===== -->
<div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 mb-8">
    <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
        <i class="fas fa-user-plus text-blue-500 text-xs"></i>
        Recent Employees
    </h3>
    <?php if (empty($recentEmployees)): ?>
    <p class="text-sm text-slate-400 text-center py-6">No employees yet</p>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100">
                    <th class="text-left text-xs font-semibold text-slate-400 uppercase tracking-wide pb-2">Employee</th>
                    <th class="text-left text-xs font-semibold text-slate-400 uppercase tracking-wide pb-2">Code</th>
                    <th class="text-left text-xs font-semibold text-slate-400 uppercase tracking-wide pb-2">Department</th>
                    <th class="text-left text-xs font-semibold text-slate-400 uppercase tracking-wide pb-2">Designation</th>
                    <th class="text-left text-xs font-semibold text-slate-400 uppercase tracking-wide pb-2">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($recentEmployees as $re): ?>
                <tr class="hover:bg-slate-50">
                    <td class="py-2.5 font-medium text-slate-800"><?= htmlspecialchars($re['first_name'] . ' ' . $re['last_name']) ?></td>
                    <td class="py-2.5 text-slate-500 font-mono text-xs"><?= htmlspecialchars($re['employee_code']) ?></td>
                    <td class="py-2.5 text-slate-600"><?= htmlspecialchars($re['department_name'] ?? 'N/A') ?></td>
                    <td class="py-2.5 text-slate-600"><?= htmlspecialchars($re['designation_name'] ?? 'N/A') ?></td>
                    <td class="py-2.5"><span class="px-2 py-0.5 text-xs rounded-full <?= $empStatusColors[$re['status']] ?? '' ?>"><?= ucfirst($re['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ===== Quick Actions ===== -->
<div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5 mb-8">
    <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
        <i class="fas fa-bolt text-indigo-500 text-xs"></i>
        Quick Actions
    </h3>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <?php if (hasPermission('employees.create')): ?>
        <a href="<?= url('modules/employees/create.php') ?>" class="flex items-center gap-3 p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
            <i class="fas fa-user-plus text-blue-600 text-xl"></i>
            <div>
                <div class="font-medium text-sm">New Employee</div>
                <div class="text-xs text-gray-500">Add staff member</div>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('attendance.create')): ?>
        <a href="<?= url('modules/attendance/create.php') ?>" class="flex items-center gap-3 p-4 bg-green-50 rounded-lg hover:bg-green-100 transition">
            <i class="fas fa-clock text-green-600 text-xl"></i>
            <div>
                <div class="font-medium text-sm">Mark Attendance</div>
                <div class="text-xs text-gray-500">Record check-in</div>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('leaves.create')): ?>
        <a href="<?= url('modules/leave_requests/create.php') ?>" class="flex items-center gap-3 p-4 bg-amber-50 rounded-lg hover:bg-amber-100 transition">
            <i class="fas fa-calendar-plus text-amber-600 text-xl"></i>
            <div>
                <div class="font-medium text-sm">Request Leave</div>
                <div class="text-xs text-gray-500">Submit request</div>
            </div>
        </a>
        <?php endif; ?>
        <?php if (hasPermission('departments.create')): ?>
        <a href="<?= url('modules/departments/create.php') ?>" class="flex items-center gap-3 p-4 bg-violet-50 rounded-lg hover:bg-violet-100 transition">
            <i class="fas fa-building text-violet-600 text-xl"></i>
            <div>
                <div class="font-medium text-sm">New Department</div>
                <div class="text-xs text-gray-500">Add department</div>
            </div>
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- ===== Charts JS ===== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const render = window.renderCarbonChart;

    // Employees by Department (bar)
    const deptEl = document.getElementById('empByDeptChart');
    if (deptEl && render) {
        const labels = <?= json_encode(array_column($employeesByDept, 'department_name')) ?>;
        const data = <?= json_encode(array_map('intval', array_column($employeesByDept, 'cnt'))) ?>;
        render(deptEl, 'bar', {
            labels: labels,
            datasets: [{ label: 'Employees', data: data, backgroundColor: '#6366f1' }]
        }, { scales: { y: { beginAtZero: true } }, legend: { enabled: false } });
    }

    // Attendance Today (doughnut)
    const attEl = document.getElementById('attendanceChart');
    if (attEl && render) {
        const attLabels = <?= json_encode(array_map(function($a) { return ucfirst($a['status']); }, $attendanceByStatus)) ?>;
        const attData = <?= json_encode(array_map('intval', array_column($attendanceByStatus, 'cnt'))) ?>;
        const attColors = ['#22c55e','#ef4444','#f59e0b','#6366f1','#94a3b8','#64748b'];
        render(attEl, 'doughnut', {
            labels: attLabels,
            datasets: [{ data: attData, backgroundColor: attColors.slice(0, attData.length), borderWidth: 0 }]
        }, { legend: { position: 'bottom' } });
    }

    // Leave by Type (bar)
    const leaveEl = document.getElementById('leaveByTypeChart');
    if (leaveEl && render) {
        const lLabels = <?= json_encode(array_column($leaveByType, 'leave_name')) ?>;
        const lData = <?= json_encode(array_map('intval', array_column($leaveByType, 'cnt'))) ?>;
        render(leaveEl, 'bar', {
            labels: lLabels,
            datasets: [{ label: 'Approved Leaves', data: lData, backgroundColor: '#f59e0b' }]
        }, { scales: { y: { beginAtZero: true } }, legend: { enabled: false } });
    }

    // Monthly Hires (line)
    const hiresEl = document.getElementById('monthlyHiresChart');
    if (hiresEl && render) {
        const hLabels = <?= json_encode(array_column($monthlyHires, 'label')) ?>;
        const hData = <?= json_encode(array_map('intval', array_column($monthlyHires, 'cnt'))) ?>;
        render(hiresEl, 'line', {
            labels: hLabels,
            datasets: [{
                label: 'New Hires',
                data: hData,
                borderColor: '#8b5cf6',
                backgroundColor: 'rgba(139,92,246,0.08)',
                fill: true,
                tension: 0.3,
                pointBackgroundColor: '#8b5cf6',
                pointRadius: 4,
                borderWidth: 2
            }]
        }, { legend: { enabled: false }, curve: 'curveMonotoneX', points: { radius: 4 } });
    }

    // Gender Distribution (doughnut)
    const genderEl = document.getElementById('genderChart');
    if (genderEl && render) {
        const gLabels = <?= json_encode(array_map(function($g) { return ucfirst($g['gender']); }, $employeesByGender)) ?>;
        const gData = <?= json_encode(array_map('intval', array_column($employeesByGender, 'cnt'))) ?>;
        const gColors = <?= json_encode(array_map(function($g) use ($genderColors) { return $genderColors[$g['gender']] ?? '#94a3b8'; }, $employeesByGender)) ?>;
        render(genderEl, 'doughnut', {
            labels: gLabels,
            datasets: [{ data: gData, backgroundColor: gColors, borderWidth: 0 }]
        }, { legend: { position: 'bottom' } });
    }

    // Employment Type (doughnut)
    const typeEl = document.getElementById('empTypeChart');
    if (typeEl && render) {
        const tLabels = <?= json_encode(array_map(function($t) use ($typeLabels) { return $typeLabels[$t['employment_type']] ?? $t['employment_type']; }, $employeesByType)) ?>;
        const tData = <?= json_encode(array_map('intval', array_column($employeesByType, 'cnt'))) ?>;
        const tColors = ['#6366f1','#22c55e','#f59e0b','#ec4899'];
        render(typeEl, 'doughnut', {
            labels: tLabels,
            datasets: [{ data: tData, backgroundColor: tColors.slice(0, tData.length), borderWidth: 0 }]
        }, { legend: { position: 'bottom' } });
    }
});
</script>

</div><!-- /dashboard-old-content -->

<?php
require_once '../../includes/dashboard_template.php';
require_once '../../includes/footer.php'; ?>
