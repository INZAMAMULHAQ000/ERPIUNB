<?php
/**
 * ERP System — Comprehensive Sample Data Seeder (PHP)
 * Run AFTER all schema files have been applied.
 * Dynamically resolves IDs so FK constraints are always satisfied.
 *
 * Usage: php config/seed_data.php
 */

$db = new PDO(
    'mysql:host=localhost;dbname=erp_db;charset=utf8mb4',
    'root',
    '',
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    ]
);

echo "=== ERP Sample Data Seeder ===\n\n";

// ---------------------------------------------------------------
// Helper: fetch a map of [name => id] from a table
// ---------------------------------------------------------------
function idMap(PDO $db, string $table, string $labelCol = 'name', string $idCol = 'id'): array {
    $map = [];
    foreach ($db->query("SELECT $idCol, $labelCol FROM $table") as $row) {
        $map[$row[$labelCol]] = (int)$row[$idCol];
    }
    return $map;
}

// ---------------------------------------------------------------
// Helper: insert and return the new ID
// ---------------------------------------------------------------
function insert(PDO $db, string $table, array $data): int {
    $cols = implode(', ', array_keys($data));
    $vals = implode(', ', array_fill(0, count($data), '?'));
    $stmt = $db->prepare("INSERT INTO $table ($cols) VALUES ($vals)");
    $stmt->execute(array_values($data));
    return (int)$db->lastInsertId();
}

// ---------------------------------------------------------------
// 1. DESIGNATIONS
// ---------------------------------------------------------------
$dept = idMap($db, 'departments', 'department_name');

$designations = [
    ['HR Manager',          $dept['Human Resources'],         'Head of Human Resources'],
    ['Recruiter',           $dept['Human Resources'],         'Talent acquisition specialist'],
    ['IT Manager',          $dept['Information Technology'],  'Head of Information Technology'],
    ['Software Developer',  $dept['Information Technology'],  'Software development engineer'],
    ['Finance Manager',     $dept['Finance'],                 'Head of Finance'],
    ['Accountant',          $dept['Finance'],                 'Accounts and bookkeeping'],
    ['Marketing Manager',   $dept['Marketing'],               'Head of Marketing'],
    ['Sales Executive',     $dept['Marketing'],               'Sales and business development'],
    ['Operations Manager',  $dept['Operations'],              'Head of Operations'],
    ['Project Coordinator', $dept['Operations'],              'Project coordination and support'],
];
$desigMap = [];
foreach ($designations as $d) {
    $id = insert($db, 'designations', [
        'designation_name' => $d[0],
        'department_id'    => $d[1],
        'description'      => $d[2],
    ]);
    $desigMap[$d[0]] = $id;
}
echo "1. Designations: " . count($designations) . " created\n";

// ---------------------------------------------------------------
// 2. EMPLOYEES  (12 employees across all departments)
// ---------------------------------------------------------------
$employeesData = [
    ['Amit',    'Sharma',   'male',   '1990-04-15', '9876543210', 'amit.sharma@erp.com',   'Mumbai',    'Human Resources',        'Recruiter',           'full_time', '2024-01-15', 'Mumbai Office',    'active'],
    ['Priya',   'Verma',    'female', '1992-08-22', '9876543211', 'priya.verma@erp.com',   'Delhi',     'Information Technology', 'IT Manager',          'full_time', '2023-06-01', 'Delhi Office',     'active'],
    ['Rahul',   'Patel',    'male',   '1988-11-10', '9876543212', 'rahul.patel@erp.com',   'Bangalore', 'Information Technology', 'Software Developer',  'full_time', '2021-03-10', 'Bangalore Office', 'active'],
    ['Sneha',   'Reddy',    'female', '1995-02-28', '9876543213', 'sneha.reddy@erp.com',   'Hyderabad', 'Finance',               'Finance Manager',     'full_time', '2022-07-20', 'Hyderabad Office', 'active'],
    ['Vikram',  'Singh',    'male',   '1985-06-05', '9876543214', 'vikram.singh@erp.com',  'Mumbai',    'Finance',               'Accountant',          'full_time', '2020-01-01', 'Mumbai Office',    'active'],
    ['Ananya',  'Gupta',    'female', '1993-09-18', '9876543215', 'ananya.gupta@erp.com',  'Delhi',     'Marketing',             'Marketing Manager',   'full_time', '2023-11-01', 'Delhi Office',     'active'],
    ['Rohit',   'Kumar',    'male',   '1991-12-12', '9876543216', 'rohit.kumar@erp.com',   'Bangalore', 'Marketing',             'Sales Executive',     'full_time', '2022-04-01', 'Bangalore Office', 'active'],
    ['Neha',    'Joshi',    'female', '1987-03-25', '9876543217', 'neha.joshi@erp.com',    'Pune',      'Operations',            'Operations Manager',  'full_time', '2019-08-15', 'Pune Office',      'active'],
    ['Karan',   'Mehta',    'male',   '1994-07-08', '9876543218', 'karan.mehta@erp.com',   'Mumbai',    'Operations',            'Project Coordinator', 'full_time', '2023-09-01', 'Mumbai Office',    'active'],
    ['Divya',   'Nair',     'female', '1996-01-30', '9876543219', 'divya.nair@erp.com',    'Chennai',   'Human Resources',       'HR Manager',          'contract',  '2024-06-01', 'Chennai Office',   'active'],
];

$empId = []; // maps index -> DB id
$code = 3;
foreach ($employeesData as $i => $e) {
    $id = insert($db, 'employees', [
        'employee_code'   => 'EMP' . date('Y') . sprintf('%04d', $code++),
        'first_name'      => $e[0],
        'last_name'       => $e[1],
        'gender'          => $e[2],
        'date_of_birth'   => $e[3],
        'phone'           => $e[4],
        'email'           => $e[5],
        'address'         => $e[6],
        'department_id'   => $dept[$e[7]],
        'designation_id'  => $desigMap[$e[8]],
        'joining_date'    => $e[9],
        'employment_type' => $e[10],
        'work_location'   => $e[11],
        'status'          => $e[12],
    ]);
    $empId[$i] = $id;
}

// Update reporting managers (dependency: need empId map populated)
$itMgrId   = $empId[1];  // Priya = IT Manager
$finMgrId  = $empId[3];  // Sneha = Finance Manager
$opsMgrId  = $empId[7];  // Neha = Operations Manager
$hrMgrId   = $empId[9];  // Divya = HR Manager

$db->exec("UPDATE employees SET reporting_manager_id = $itMgrId  WHERE department_id = {$dept['Information Technology']} AND id != $itMgrId");
$db->exec("UPDATE employees SET reporting_manager_id = $finMgrId WHERE department_id = {$dept['Finance']} AND id != $finMgrId");
$db->exec("UPDATE employees SET reporting_manager_id = $opsMgrId WHERE department_id = {$dept['Operations']} AND id != $opsMgrId");
$db->exec("UPDATE employees SET reporting_manager_id = $hrMgrId  WHERE department_id = {$dept['Human Resources']} AND id != $hrMgrId");

echo "2. Employees: " . count($employeesData) . " created\n";

// ---------------------------------------------------------------
// 3. LEAVE BALANCES
// ---------------------------------------------------------------
$leaveTypes = idMap($db, 'leave_types', 'leave_code');
$balCount = 0;
$allEmpRows = $db->query("SELECT id FROM employees WHERE status='active'")->fetchAll();
foreach ($allEmpRows as $emp) {
    foreach ($leaveTypes as $code => $ltId) {
        $used = match ($code) {
            'CL' => 3, 'SL' => 2, 'EL' => 5,
            default => 0,
        };
        $total = $db->query("SELECT total_days FROM leave_types WHERE id = $ltId")->fetch()['total_days'];
        insert($db, 'leave_balances', [
            'employee_id'  => $emp['id'],
            'leave_type_id' => $ltId,
            'year'         => date('Y'),
            'total_days'   => $total,
            'used_days'    => $used,
        ]);
        $balCount++;
    }
}
echo "3. Leave Balances: $balCount created\n";

// ---------------------------------------------------------------
// 4. ATTENDANCE (last 30 days)
// ---------------------------------------------------------------
$attCount = 0;
$today = new DateTime();
for ($d = 30; $d >= 1; $d--) {
    $date = (clone $today)->modify("-{$d} days");
    $dayOfWeek = (int)$date->format('N'); // 1=Mon .. 7=Sun
    $dateStr = $date->format('Y-m-d');

    foreach ($allEmpRows as $emp) {
        if ($dayOfWeek >= 6 && mt_rand(1, 100) > 20) continue; // 20% weekend work
        if (mt_rand(1, 100) > 92) continue;                     // ~8% absent

        $status = 'present';
        $checkIn = '09:00:00';
        $checkOut = '18:00:00';
        $lateMin = 0;

        if ($dayOfWeek >= 6) {
            $status = 'weekend';
            $checkIn = $checkOut = null;
        } elseif (mt_rand(1, 100) <= 10) {
            $status = 'late';
            $checkIn = '09:' . str_pad((string)mt_rand(15, 50), 2, '0', STR_PAD_LEFT) . ':00';
            $lateMin = (int)substr($checkIn, 3, 2) - 0 + 60; // approx late minutes
        }

        insert($db, 'attendance', [
            'employee_id'      => $emp['id'],
            'attendance_date'  => $dateStr,
            'check_in'         => $checkIn,
            'check_out'        => $checkOut,
            'status'           => $status,
            'late_minutes'     => $lateMin,
        ]);
        $attCount++;
    }
}
echo "4. Attendance: $attCount records created\n";

// ---------------------------------------------------------------
// 5. LEAVE REQUESTS
// ---------------------------------------------------------------
$leaveReq = [
    [$empId[0], 'SL', -10, -8,  'Medical appointment',       'approved', 1],
    [$empId[3], 'CL', -5,  -3,  'Personal work',              'approved', 1],
    [$empId[4], 'EL', 10,  14,  'Annual vacation',            'pending',  null],
    [$empId[5], 'SL', -3,  -1,  'Sick leave',                 'rejected', 1],
    [$empId[7], 'CL', 5,   5,   'Personal errand',            'pending',  null],
    [$empId[2], 'EM', -20, -17, 'Family emergency',           'approved', 1],
];
$lrCount = 0;
$todayDT = new DateTime();
foreach ($leaveReq as $lr) {
    $from = (clone $todayDT)->modify(($lr[2] < 0 ? '' : '+') . $lr[2] . ' days')->format('Y-m-d');
    $to   = (clone $todayDT)->modify(($lr[3] < 0 ? '' : '+') . $lr[3] . ' days')->format('Y-m-d');
    $days = (new DateTime($from))->diff(new DateTime($to))->days + 1;
    $id = insert($db, 'leave_requests', [
        'employee_id'   => $lr[0],
        'leave_type_id' => $leaveTypes[$lr[1]],
        'from_date'     => $from,
        'to_date'       => $to,
        'total_days'    => $days,
        'reason'        => $lr[4],
        'status'        => $lr[5],
        'approved_by'   => $lr[6],
    ]);
    $lrCount++;
}
echo "5. Leave Requests: $lrCount created\n";

// ---------------------------------------------------------------
// 6. PROJECTS
// ---------------------------------------------------------------
$projects = [
    ['PRJ' . date('Y') . '001', 'Website Redesign',       'TechCorp Inc.',    'Complete overhaul of corporate website',                       '2026-01-15', '2026-04-30', 500,  $empId[1], 'completed', 'high'],
    ['PRJ' . date('Y') . '002', 'Mobile App Development',  'FinServe Ltd.',    'iOS and Android banking application',                           '2026-03-01', '2026-08-31', 1200, $empId[1], 'active',    'critical'],
    ['PRJ' . date('Y') . '003', 'Data Migration',          'HealthCare Plus',  'Migrate legacy data to new cloud platform',                     '2026-04-01', '2026-07-15', 400,  $empId[2], 'active',    'high'],
    ['PRJ' . date('Y') . '004', 'ERP Integration',         'GlobalMart',       'Integrate ERP with third-party logistics provider',            '2026-05-01', '2026-10-31', 800,  $empId[1], 'active',    'medium'],
    ['PRJ' . date('Y') . '005', 'Internal Dashboard',      null,               'Build internal analytics dashboard for management',            '2026-06-01', '2026-08-15', 300,  $empId[5], 'planning',  'medium'],
];
$projIds = [];
foreach ($projects as $p) {
    $id = insert($db, 'projects', [
        'project_code'        => $p[0],
        'project_name'        => $p[1],
        'client_name'         => $p[2],
        'description'         => $p[3],
        'start_date'          => $p[4],
        'end_date'            => $p[5],
        'estimated_hours'     => $p[6],
        'project_manager_id'  => $p[7],
        'status'              => $p[8],
        'priority'            => $p[9],
    ]);
    $projIds[$p[1]] = $id;
}
echo "6. Projects: " . count($projects) . " created\n";

// ---------------------------------------------------------------
// 7. PROJECT MEMBERS
// ---------------------------------------------------------------
$pmCount = 0;
$pmData = [
    ['Website Redesign',      [$empId[1], $empId[2], $empId[3], $empId[8]]],
    ['Mobile App Development', [$empId[1], $empId[2], $empId[3], $empId[8]]],
    ['Data Migration',         [$empId[2], $empId[3], $empId[7]]],
    ['ERP Integration',        [$empId[1], $empId[2], $empId[3]]],
    ['Internal Dashboard',     [$empId[5], $empId[6], $empId[8]]],
];
$roles = ['Project Manager', 'Lead Developer', 'Developer', 'Tester', 'Analyst', 'Designer'];
foreach ($pmData as $pm) {
    $pid = $projIds[$pm[0]];
    foreach ($pm[1] as $eid) {
        insert($db, 'project_members', [
            'project_id'  => $pid,
            'employee_id' => $eid,
            'member_role' => $roles[array_rand($roles)],
        ]);
        $pmCount++;
    }
}
echo "7. Project Members: $pmCount created\n";

// ---------------------------------------------------------------
// 8. MILESTONES
// ---------------------------------------------------------------
$milestones = [
    ['Website Redesign',      'Design Approval',      '2026-02-01', 'completed'],
    ['Website Redesign',      'Development Complete', '2026-03-15', 'completed'],
    ['Website Redesign',      'UAT Sign-off',         '2026-04-15', 'completed'],
    ['Mobile App Development','MVP Release',          '2026-05-30', 'completed'],
    ['Mobile App Development','Beta Testing',         '2026-07-15', 'in_progress'],
    ['Mobile App Development','Production Launch',    '2026-08-31', 'pending'],
    ['Data Migration',        'Schema Design',        '2026-04-15', 'completed'],
    ['Data Migration',        'Migration Complete',   '2026-06-30', 'in_progress'],
    ['ERP Integration',       'API Integration',      '2026-07-01', 'pending'],
    ['Internal Dashboard',    'Requirements',         '2026-06-15', 'pending'],
];
$msIds = [];
foreach ($milestones as $m) {
    $id = insert($db, 'milestones', [
        'project_id'      => $projIds[$m[0]],
        'milestone_name'  => $m[1],
        'due_date'        => $m[2],
        'status'          => $m[3],
    ]);
    $msIds[$m[1]] = $id;
}
echo "8. Milestones: " . count($milestones) . " created\n";

// ---------------------------------------------------------------
// 9. TASKS
// ---------------------------------------------------------------
$tasks = [
    ['Website Redesign',      'Design Approval',      $empId[8], 'Create wireframes',    'Homepage, about, contact wireframes',  '2026-01-15', '2026-01-25', 20,  'completed', 'medium', 100],
    ['Website Redesign',      'Development Complete', $empId[2], 'Develop homepage',     'Build homepage with Tailwind',         '2026-02-01', '2026-02-20', 40,  'completed', 'high',   100],
    ['Website Redesign',      'Development Complete', $empId[3], 'Develop admin panel',  'Admin dashboard and CMS',              '2026-02-10', '2026-03-10', 60,  'completed', 'high',   100],
    ['Mobile App Development','MVP Release',          $empId[2], 'Setup project',        'Initialize React Native project',      '2026-03-01', '2026-03-10', 16,  'completed', 'high',   100],
    ['Mobile App Development','MVP Release',          $empId[3], 'Auth module',          'Login, register, password reset',      '2026-03-10', '2026-03-30', 40,  'completed', 'critical',100],
    ['Mobile App Development','Beta Testing',          $empId[2], 'Dashboard screens',   'User dashboard with account summary',  '2026-06-01', '2026-06-20', 60,  'in_progress', 'high',  50],
    ['Mobile App Development','Beta Testing',          $empId[3], 'Payment integration', 'UPI and card payment gateway',         '2026-06-10', '2026-07-10', 80,  'todo',     'critical', 0],
    ['Data Migration',        'Schema Design',        $empId[3], 'Schema analysis',      'Analyze legacy schema and map fields', '2026-04-01', '2026-04-10', 20,  'completed', 'high',   100],
    ['Data Migration',        'Migration Complete',   $empId[2], 'ETL scripts',          'Write ETL scripts for data migration', '2026-05-01', '2026-06-15', 80,  'in_progress', 'high',  40],
    ['ERP Integration',       'API Integration',      $empId[2], 'API documentation',    'Document third-party API endpoints',   '2026-05-15', '2026-05-30', 24,  'completed', 'medium', 100],
    ['ERP Integration',       'API Integration',      $empId[3], 'Integration code',     'Write integration layer',              '2026-06-01', '2026-07-01', 60,  'in_progress', 'high',  30],
    ['Internal Dashboard',    'Requirements',         $empId[6], 'Gather requirements',  'Interview stakeholders',               '2026-06-01', '2026-06-10', 16,  'completed', 'medium', 100],
    ['Internal Dashboard',    'Requirements',         $empId[5], 'Design mockups',       'Dashboard layout and chart designs',   '2026-06-10', '2026-06-20', 24,  'in_progress', 'medium', 20],
];
$taskIds = [];
foreach ($tasks as $t) {
    $id = insert($db, 'tasks', [
        'project_id'         => $projIds[$t[0]],
        'milestone_id'       => $msIds[$t[1]],
        'assigned_to'        => $t[2],
        'task_title'         => $t[3],
        'task_description'   => $t[4],
        'start_date'         => $t[5],
        'due_date'           => $t[6],
        'estimated_hours'    => $t[7],
        'status'             => $t[8],
        'priority'           => $t[9],
        'progress_percentage'=> $t[10],
    ]);
    $taskIds[$t[3]] = $id;
}
echo "9. Tasks: " . count($tasks) . " created\n";

// ---------------------------------------------------------------
// 10. TASK COMMENTS
// ---------------------------------------------------------------
$taskComments = [
    ['Create wireframes',   'Wireframes have been reviewed and approved by the client.',           '2026-01-24 14:30:00'],
    ['Develop homepage',    'Homepage is looking great. Minor changes needed in the hero section.', '2026-02-18 10:15:00'],
    ['Auth module',         'Auth module needs to support OAuth2 as well.',                         '2026-03-25 11:00:00'],
    ['Payment integration', 'Please prioritize payment gateway integration.',                        '2026-06-05 09:00:00'],
];
foreach ($taskComments as $tc) {
    insert($db, 'task_comments', [
        'task_id'    => $taskIds[$tc[0]],
        'user_id'    => 1,
        'comment'    => $tc[1],
        'created_at' => $tc[2],
    ]);
}
echo "10. Task Comments: " . count($taskComments) . " created\n";

// ---------------------------------------------------------------
// 11. TIMESHEETS
// ---------------------------------------------------------------
$tsCount = 0;
for ($d = 14; $d >= 2; $d--) {
    $date = (clone $today)->modify("-{$d} days");
    if ((int)$date->format('N') >= 6) continue; // skip weekends
    $dateStr = $date->format('Y-m-d');
    $allEmp = $db->query("SELECT e.id, pm.project_id FROM employees e JOIN project_members pm ON e.id = pm.employee_id WHERE e.status='active'")->fetchAll();
    foreach ($allEmp as $e) {
        if (mt_rand(1, 100) > 60) continue;
        $taskRows = $db->query("SELECT id FROM tasks WHERE project_id = {$e['project_id']} AND assigned_to = {$e['id']}")->fetchAll();
        if (empty($taskRows)) continue;
        $tid = $taskRows[array_rand($taskRows)]['id'];
        insert($db, 'timesheets', [
            'employee_id'      => $e['id'],
            'project_id'       => $e['project_id'],
            'task_id'          => $tid,
            'work_date'        => $dateStr,
            'hours_logged'     => round(4 + mt_rand(0, 40) / 10, 1),
            'work_description' => 'Worked on project tasks',
        ]);
        $tsCount++;
    }
}
echo "11. Timesheets: $tsCount created\n";

// ---------------------------------------------------------------
// 12. LEADS
// ---------------------------------------------------------------
$leads = [
    ['LDS' . date('Y') . '001', 'TechCorp Inc.',      'John Doe',    'john@techcorp.com',   '+1-555-0101', 'USA',     'Website',   $empId[1], 'won',       'high',   150000, 'Large enterprise deal'],
    ['LDS' . date('Y') . '002', 'FinServe Ltd.',       'Jane Smith',  'jane@finserve.com',   '+1-555-0102', 'UK',      'Referral',  $empId[1], 'won',       'high',   250000, 'Banking sector lead'],
    ['LDS' . date('Y') . '003', 'HealthCare Plus',     'Dr. Brown',   'drbrown@hcp.com',     '+1-555-0103', 'USA',     'Conference',$empId[2], 'qualified', 'medium', 80000,  'Compliance requirements pending'],
    ['LDS' . date('Y') . '004', 'GlobalMart',          'Mike Wilson', 'mike@globalmart.com',  '+1-555-0104', 'Canada',  'LinkedIn',  $empId[1], 'negotiation','high', 300000, 'Multi-year contract'],
    ['LDS' . date('Y') . '005', 'EduSoft Solutions',   'Sarah Lee',   'sarah@edusoft.com',   '+1-555-0105', 'Australia','Email',    $empId[4], 'new',       'low',   50000,  'Education sector lead'],
    ['LDS' . date('Y') . '006', 'CloudBase Systems',   'Tom Chen',    'tom@cloudbase.io',    '+1-555-0106', 'USA',     'Website',   $empId[2], 'proposal_sent','medium', 120000, 'Interested in cloud migration'],
    ['LDS' . date('Y') . '007', 'DataVault Inc.',      'Lisa Adams',  'lisa@datavault.com',  '+1-555-0107', 'UK',      'Referral',  $empId[4], 'lost',      'medium', 60000,  'Chose competitor'],
    ['LDS' . date('Y') . '008', 'GreenEnergy Corp',    'Raj Kumar',   'raj@greenenergy.com','+1-555-0108', 'India',   'Trade Show',$empId[1], 'contacted', 'high',   180000, 'Good potential for long-term'],
];
$leadIds = [];
foreach ($leads as $l) {
    $id = insert($db, 'leads', [
        'lead_code'       => $l[0],
        'company_name'    => $l[1],
        'contact_person'  => $l[2],
        'email'           => $l[3],
        'phone'           => $l[4],
        'country'         => $l[5],
        'source'          => $l[6],
        'assigned_to'     => $l[7],
        'status'          => $l[8],
        'priority'        => $l[9],
        'expected_value'  => $l[10],
        'notes'           => $l[11],
    ]);
    $leadIds[$l[1]] = $id;
}
echo "12. Leads: " . count($leads) . " created\n";

// ---------------------------------------------------------------
// 13. CLIENTS
// ---------------------------------------------------------------
$clientsData = [
    ['CLT' . date('Y') . '001', 'TechCorp Inc.',     'John Doe',     'john@techcorp.com',  '+1-555-0101', '123 Tech Street, SF',    'USA',     'https://techcorp.com',    'Technology', $empId[1], 'active'],
    ['CLT' . date('Y') . '002', 'FinServe Ltd.',     'Jane Smith',   'jane@finserve.com',  '+1-555-0102', '456 Finance Ave, London','UK',      'https://finserve.com',    'Finance',    $empId[1], 'active'],
    ['CLT' . date('Y') . '003', 'HealthCare Plus',   'Dr. Brown',    'drbrown@hcp.com',    '+1-555-0103', '789 Medical Blvd, NY',   'USA',     'https://hcp.com',         'Healthcare', $empId[2], 'active'],
    ['CLT' . date('Y') . '004', 'GlobalMart',        'Mike Wilson',  'mike@globalmart.com', '+1-555-0104', '321 Retail Dr, Toronto', 'Canada',  'https://globalmart.com',  'Retail',     $empId[1], 'active'],
    ['CLT' . date('Y') . '005', 'EduSoft Solutions', 'Sarah Lee',    'sarah@edusoft.com',  '+1-555-0105', '654 Learning Ln, Sydney','Australia','https://edusoft.com',   'Education',  $empId[4], 'active'],
];
$clientIds = [];
foreach ($clientsData as $c) {
    $id = insert($db, 'clients', [
        'client_code'      => $c[0],
        'company_name'     => $c[1],
        'contact_person'   => $c[2],
        'email'            => $c[3],
        'phone'            => $c[4],
        'address'          => $c[5],
        'country'          => $c[6],
        'website'          => $c[7],
        'industry'         => $c[8],
        'assigned_manager' => $c[9],
        'status'           => $c[10],
    ]);
    $clientIds[$c[1]] = $id;
}
echo "13. Clients: " . count($clientsData) . " created\n";

// ---------------------------------------------------------------
// 14. DEALS (Sales Pipeline)
// ---------------------------------------------------------------
$deals = [
    ['Website Redesign Contract',  $leadIds['TechCorp Inc.'],    $clientIds['TechCorp Inc.'],    $empId[1], 150000, 'closed_won',  '2026-01-15', 100, 'Successfully completed'],
    ['Mobile Banking App',         $leadIds['FinServe Ltd.'],    $clientIds['FinServe Ltd.'],    $empId[1], 250000, 'closed_won',  '2026-02-28', 100, 'Ongoing project'],
    ['Cloud Migration Project',    $leadIds['HealthCare Plus'],  $clientIds['HealthCare Plus'],  $empId[2], 80000,  'qualified',   '2026-07-30', 40,  'Needs compliance review'],
    ['ERP Integration Deal',       $leadIds['GlobalMart'],       $clientIds['GlobalMart'],       $empId[1], 300000, 'negotiation', '2026-08-15', 70,  'Negotiating terms'],
    ['LMS Platform',               $leadIds['EduSoft Solutions'],$clientIds['EduSoft Solutions'], $empId[4], 50000,  'proposal',    '2026-09-30', 25,  'Proposal submitted'],
    ['Data Analytics Suite',       $leadIds['CloudBase Systems'], null,                           $empId[2], 120000, 'qualified',   '2026-08-20', 35,  'Technical demo completed'],
    ['Infrastructure Upgrade',     $leadIds['GreenEnergy Corp'],  null,                           $empId[1], 180000, 'inquiry',     '2026-10-15', 15,  'Initial discussion done'],
];
foreach ($deals as $d) {
    insert($db, 'deals', [
        'deal_title'            => $d[0],
        'lead_id'               => $d[1],
        'client_id'             => $d[2],
        'assigned_to'           => $d[3],
        'deal_value'            => $d[4],
        'stage'                 => $d[5],
        'expected_closing_date' => $d[6],
        'probability'           => $d[7],
        'notes'                 => $d[8],
    ]);
}
echo "14. Deals: " . count($deals) . " created\n";

// ---------------------------------------------------------------
// 15. INVOICES
// ---------------------------------------------------------------
$invoices = [
    ['INV-2026-001', $clientIds['TechCorp Inc.'],    $projIds['Website Redesign'],      '2026-02-01', '2026-03-01',  50000,  9000,  0,  59000,  59000,  0,    'paid'],
    ['INV-2026-002', $clientIds['TechCorp Inc.'],    $projIds['Website Redesign'],      '2026-03-01', '2026-04-01',  50000,  9000,  0,  59000,  59000,  0,    'paid'],
    ['INV-2026-003', $clientIds['TechCorp Inc.'],    $projIds['Website Redesign'],      '2026-04-01', '2026-05-01',  50000,  9000,  0,  59000,  59000,  0,    'paid'],
    ['INV-2026-004', $clientIds['FinServe Ltd.'],    $projIds['Mobile App Development'], '2026-04-01', '2026-05-01',  80000,  14400, 2000, 92400, 92400, 0,    'paid'],
    ['INV-2026-005', $clientIds['FinServe Ltd.'],    $projIds['Mobile App Development'], '2026-06-01', '2026-07-01',  100000, 18000, 0,  118000, 50000, 68000, 'partially_paid'],
    ['INV-2026-006', $clientIds['HealthCare Plus'],  $projIds['Data Migration'],         '2026-05-01', '2026-06-01',  30000,  5400,  0,  35400,  0,     35400, 'sent'],
    ['INV-2026-007', $clientIds['GlobalMart'],       $projIds['ERP Integration'],        '2026-06-01', '2026-07-15',  15000,  2700,  0,  17700,  0,     17700, 'draft'],
    ['INV-2026-008', $clientIds['FinServe Ltd.'],    $projIds['Mobile App Development'], '2025-12-01', '2025-12-31',  50000,  9000,  0,  59000,  40000, 19000, 'overdue'],
];
$invIds = [];
foreach ($invoices as $inv) {
    $id = insert($db, 'invoices', [
        'invoice_number'  => $inv[0],
        'client_id'       => $inv[1],
        'project_id'      => $inv[2],
        'invoice_date'    => $inv[3],
        'due_date'        => $inv[4],
        'subtotal'        => $inv[5],
        'tax_amount'      => $inv[6],
        'discount_amount' => $inv[7],
        'total_amount'    => $inv[8],
        'paid_amount'     => $inv[9],
        'balance_amount'  => $inv[10],
        'status'          => $inv[11],
    ]);
    $invIds[$inv[0]] = $id;
}
echo "15. Invoices: " . count($invoices) . " created\n";

// ---------------------------------------------------------------
// 16. INVOICE ITEMS
// ---------------------------------------------------------------
$invItems = [
    ['INV-2026-001', 'Web Design',    'Homepage and 5 inner pages design',        1, 20000, 18, 20000],
    ['INV-2026-001', 'Frontend Dev',  'HTML/CSS/JS development',                  1, 30000, 18, 30000],
    ['INV-2026-002', 'Backend API',   'REST API development',                     1, 30000, 18, 30000],
    ['INV-2026-002', 'CMS Integration','Content management system',               1, 20000, 18, 20000],
    ['INV-2026-003', 'Testing & UAT','Quality assurance and user testing',        1, 25000, 18, 25000],
    ['INV-2026-003', 'Deployment',    'Server setup and deployment',              1, 25000, 18, 25000],
    ['INV-2026-004', 'Mobile App MVP','Banking mobile app MVP',                   1, 80000, 18, 80000],
    ['INV-2026-005', 'Sprint 2 Dev',  'Dashboard and payment screens',            1, 60000, 18, 60000],
    ['INV-2026-005', 'QA Testing',    'Sprint 2 testing phase',                   1, 40000, 18, 40000],
    ['INV-2026-006', 'Consulting',    'Data migration planning',                  20, 1500, 18, 30000],
    ['INV-2026-007', 'Integration',   'API integration initial phase',            10, 1500, 18, 15000],
];
foreach ($invItems as $ii) {
    insert($db, 'invoice_items', [
        'invoice_id'    => $invIds[$ii[0]],
        'item_name'     => $ii[1],
        'description'   => $ii[2],
        'quantity'      => $ii[3],
        'unit_price'    => $ii[4],
        'tax_percentage'=> $ii[5],
        'total'         => $ii[6],
    ]);
}
echo "16. Invoice Items: " . count($invItems) . " created\n";

// ---------------------------------------------------------------
// 17. PAYMENTS
// ---------------------------------------------------------------
$payments = [
    ['INV-2026-001', '2026-02-20', 59000, 'bank_transfer', 'TRX001', 'Full payment', 1],
    ['INV-2026-002', '2026-03-18', 59000, 'bank_transfer', 'TRX002', 'Full payment', 1],
    ['INV-2026-003', '2026-04-22', 59000, 'bank_transfer', 'TRX003', 'Full payment', 1],
    ['INV-2026-004', '2026-04-28', 92400, 'upi',           'UPI001', 'Full payment', 1],
    ['INV-2026-005', '2026-06-05', 50000, 'credit_card',   'CC001',  'Partial payment', 1],
    ['INV-2026-008', '2025-12-15', 40000, 'bank_transfer', 'TRX004', 'Partial payment', 1],
];
foreach ($payments as $pmt) {
    insert($db, 'payments', [
        'invoice_id'       => $invIds[$pmt[0]],
        'payment_date'     => $pmt[1],
        'amount'           => $pmt[2],
        'payment_method'   => $pmt[3],
        'reference_number' => $pmt[4],
        'notes'            => $pmt[5],
        'received_by'      => $pmt[6],
    ]);
}
echo "17. Payments: " . count($payments) . " created\n";

// ---------------------------------------------------------------
// 18. EXPENSES
// ---------------------------------------------------------------
$expCat = idMap($db, 'expense_categories', 'category_name');
$expenses = [
    ['EXP-' . date('Y') . '-001', $expCat['Internet'],            '2026-01-05', 1500,  'upi',          'Airtel',       'Monthly internet bill',          'approved', 1],
    ['EXP-' . date('Y') . '-002', $expCat['Salary'],              '2026-01-31', 45000,'bank_transfer', 'Payroll',      'January salaries',               'approved', 1],
    ['EXP-' . date('Y') . '-003', $expCat['Utilities'],           '2026-02-10', 8000, 'bank_transfer', 'Tata Power',   'February electricity bill',      'approved', 1],
    ['EXP-' . date('Y') . '-004', $expCat['Software'],            '2026-02-15', 12000,'credit_card',  'Microsoft',    'Office 365 annual subscription', 'approved', 1],
    ['EXP-' . date('Y') . '-005', $expCat['Travel'],              '2026-03-05', 25000,'credit_card',  'MakeMyTrip',   'Flight tickets - team travel',   'approved', 1],
    ['EXP-' . date('Y') . '-006', $expCat['Marketing'],           '2026-03-20', 35000,'bank_transfer', 'Google Ads',   'March advertising campaign',     'approved', 1],
    ['EXP-' . date('Y') . '-007', $expCat['Office Supplies'],     '2026-04-01', 3000, 'cash',         'Stationery Shop','Office supplies',              'pending',  1],
    ['EXP-' . date('Y') . '-008', $expCat['Hardware'],            '2026-04-15', 55000,'bank_transfer', 'Dell Inc.',    'New laptops for developers',     'approved', 1],
    ['EXP-' . date('Y') . '-009', $expCat['Salary'],              '2026-05-31', 45000,'bank_transfer', 'Payroll',      'May salaries',                   'approved', 1],
    ['EXP-' . date('Y') . '-010', $expCat['Professional Services'],'2026-06-01',15000,'bank_transfer', 'KPMG',         'Audit consulting fees',          'pending',  1],
];
foreach ($expenses as $ex) {
    insert($db, 'expenses', [
        'expense_code'  => $ex[0],
        'category_id'   => $ex[1],
        'expense_date'  => $ex[2],
        'amount'        => $ex[3],
        'payment_method'=> $ex[4],
        'vendor_name'   => $ex[5],
        'description'   => $ex[6],
        'status'        => $ex[7],
        'created_by'    => $ex[8],
    ]);
}
echo "18. Expenses: " . count($expenses) . " created\n";

// ---------------------------------------------------------------
// 19. VENDORS
// ---------------------------------------------------------------
$vendorsData = [
    ['TechSupply Co.',   'Rajesh Kumar', 'rajesh@techsupply.com', '+91-98765-43210', 'Mumbai, India', 'https://techsupply.com',  'Primary hardware vendor',        'active'],
    ['CloudHost Inc.',   'Alice Wang',   'alice@cloudhost.com',   '+1-555-0201',     'San Jose, USA',  'https://cloudhost.com',   'Cloud infrastructure provider',  'active'],
    ['SoftLicense Pro',  'Bob Martin',   'bob@softlicense.com',   '+1-555-0202',     'Austin, USA',    'https://softlicense.com', 'Software licensing distributor',  'active'],
    ['OfficeMart',       'Sita Devi',    'sita@officemart.com',   '+91-98765-43211', 'Delhi, India',   'https://officemart.com',  'Office supplies vendor',         'active'],
    ['NetConnect',       'David Park',   'david@netconnect.com',  '+1-555-0203',     'Seattle, USA',   null,                     'Networking equipment supplier',   'inactive'],
];
$vendorIds = [];
foreach ($vendorsData as $v) {
    $id = insert($db, 'vendors', [
        'vendor_name'    => $v[0],
        'contact_person' => $v[1],
        'email'          => $v[2],
        'phone'          => $v[3],
        'address'        => $v[4],
        'website'        => $v[5],
        'notes'          => $v[6],
        'status'         => $v[7],
    ]);
    $vendorIds[$v[0]] = $id;
}
echo "19. Vendors: " . count($vendorsData) . " created\n";

// ---------------------------------------------------------------
// 20. ASSETS (additional, plus fix existing)
// ---------------------------------------------------------------
$assetCat = idMap($db, 'asset_categories', 'category_name');
$db->exec("DELETE FROM assets"); // clear the dummy asset

$assetsData = [
    ['AST' . date('Y') . '002', 'MacBook Pro 16"',  $assetCat['Laptop'],           'SN-MBP-001', 'Apple',  'M3 Pro',    '2026-01-15', 1999.00, $vendorIds['TechSupply Co.'], '2027-01-15', 'new',  'assigned'],
    ['AST' . date('Y') . '003', 'ThinkPad X1',      $assetCat['Laptop'],           'SN-TP-002',  'Lenovo', 'Gen 11',   '2026-02-01', 1599.00, $vendorIds['TechSupply Co.'], '2027-02-01', 'new',  'assigned'],
    ['AST' . date('Y') . '004', 'Dell UltraSharp',  $assetCat['Monitor'],          'SN-MON-001', 'Dell',   'U2723QE',  '2025-11-01', 619.00,  $vendorIds['TechSupply Co.'], '2027-11-01', 'good', 'assigned'],
    ['AST' . date('Y') . '005', 'Office Server',    $assetCat['Server'],           'SN-SRV-001', 'HP',     'DL380 Gen11','2025-06-01',8500.00, $vendorIds['TechSupply Co.'], '2028-06-01', 'good', 'available'],
    ['AST' . date('Y') . '006', 'iPhone 15 Pro',    $assetCat['Mobile'],           'SN-IPH-001', 'Apple',  'A3101',     '2026-03-01', 1099.00, $vendorIds['CloudHost Inc.'],  '2027-03-01', 'new',  'assigned'],
    ['AST' . date('Y') . '007', 'Samsung Galaxy S24',$assetCat['Mobile'],          'SN-SAM-001', 'Samsung','S24 Ultra', '2026-03-01', 1199.00, $vendorIds['CloudHost Inc.'],  '2027-03-01', 'new',  'available'],
    ['AST' . date('Y') . '008', 'HP LaserJet Pro',  $assetCat['Printer'],          'SN-PRT-001', 'HP',     'M404dn',    '2025-08-01', 399.00,  $vendorIds['TechSupply Co.'], '2027-08-01', 'good', 'available'],
    ['AST' . date('Y') . '009', 'Cisco Switch',     $assetCat['Network'],          'SN-CSW-001', 'Cisco',  'Catalyst 9300','2025-05-01',2500.00,$vendorIds['NetConnect'],     '2028-05-01', 'good', 'available'],
    ['AST' . date('Y') . '010', 'Logitech MX Keys', $assetCat['Accessories'],      'SN-ACC-001', 'Logitech','MX Keys',  '2026-01-15', 99.99,  $vendorIds['TechSupply Co.'], '2027-01-15', 'good', 'assigned'],
];
$assetIds = [];
foreach ($assetsData as $a) {
    $id = insert($db, 'assets', [
        'asset_code'      => $a[0],
        'asset_name'      => $a[1],
        'category_id'     => $a[2],
        'serial_number'   => $a[3],
        'brand'           => $a[4],
        'model'           => $a[5],
        'purchase_date'   => $a[6],
        'purchase_cost'   => $a[7],
        'vendor_id'       => $a[8],
        'warranty_expiry' => $a[9],
        'asset_condition' => $a[10],
        'status'          => $a[11],
    ]);
    $assetIds[$a[1]] = $id;
}
echo "20. Assets: " . count($assetsData) . " created\n";

// ---------------------------------------------------------------
// 21. ASSET ASSIGNMENTS
// ---------------------------------------------------------------
$assignments = [
    [$assetIds['MacBook Pro 16"'], $empId[1], '2026-01-20', null, 'active'],
    [$assetIds['ThinkPad X1'],     $empId[2], '2026-02-05', null, 'active'],
    [$assetIds['Dell UltraSharp'], $empId[1], '2025-11-15', null, 'active'],
    [$assetIds['iPhone 15 Pro'],   $empId[0], '2026-03-05', null, 'active'],
    [$assetIds['Logitech MX Keys'],$empId[8], '2026-01-20', null, 'active'],
];
foreach ($assignments as $asgn) {
    insert($db, 'asset_assignments', [
        'asset_id'       => $asgn[0],
        'employee_id'    => $asgn[1],
        'assigned_date'  => $asgn[2],
        'expected_return_date' => $asgn[3],
        'assigned_by'    => 1,
        'status'         => $asgn[4],
    ]);
}
echo "21. Asset Assignments: " . count($assignments) . " created\n";

// ---------------------------------------------------------------
// 22. ASSET MAINTENANCE
// ---------------------------------------------------------------
$maintenanceData = [
    [$assetIds['Office Server'],    '2026-03-15', 'inspection', 'HP Services',   200,  'Quarterly server inspection',  '2026-06-15', 'completed'],
    [$assetIds['HP LaserJet Pro'],  '2026-04-01', 'repair',     'HP Authorized', 150,  'Printer roller replacement',   '2026-10-01', 'completed'],
    [$assetIds['Cisco Switch'],     '2026-05-01', 'upgrade',    'Cisco Engineer',500,  'Firmware upgrade',             '2026-11-01', 'completed'],
    [$assetIds['Office Server'],    '2026-06-01', 'cleaning',   'In-house',      0,    'Server room cleaning',         '2026-07-01', 'completed'],
];
foreach ($maintenanceData as $m) {
    insert($db, 'asset_maintenance', [
        'asset_id'           => $m[0],
        'maintenance_date'   => $m[1],
        'maintenance_type'   => $m[2],
        'vendor_name'        => $m[3],
        'cost'               => $m[4],
        'description'        => $m[5],
        'next_maintenance_date' => $m[6],
        'status'             => $m[7],
    ]);
}
echo "22. Maintenance Records: " . count($maintenanceData) . " created\n";

// ---------------------------------------------------------------
// 23. SOFTWARE LICENSES
// ---------------------------------------------------------------
$licenses = [
    ['Microsoft 365 Business',  'M365-ERP-001',  '2026-01-01', '2026-12-31', 50,  'Microsoft',  'active'],
    ['JetBrains All Products',  'JB-ERP-002',    '2026-02-01', '2027-01-31', 10,  'JetBrains',  'active'],
    ['Adobe Creative Cloud',    'ADOBE-ERP-003', '2026-01-15', '2026-07-15', 5,   'Adobe',      'active'],
    ['Slack Enterprise',        'SLACK-ERP-004', '2025-06-01', '2026-06-01', 100, 'Slack',      'expired'],
    ['AWS Credits',             'AWS-ERP-005',   '2026-01-01', '2026-06-30', 1,   'AWS',        'expired'],
];
foreach ($licenses as $lic) {
    insert($db, 'software_licenses', [
        'software_name' => $lic[0],
        'license_key'   => $lic[1],
        'purchase_date' => $lic[2],
        'expiry_date'   => $lic[3],
        'seats'         => $lic[4],
        'vendor'        => $lic[5],
        'status'        => $lic[6],
    ]);
}
echo "23. Software Licenses: " . count($licenses) . " created\n";

// ---------------------------------------------------------------
// 24. INVENTORY ITEMS
// ---------------------------------------------------------------
$invItemsData = [
    ['A4 Paper Ream',       'INV-001', 'Stationery',   50,  10,  5.00,  $vendorIds['OfficeMart'],     'Standard 80gsm A4 paper',           'active'],
    ['Toner Cartridge HP',  'INV-002', 'Consumables',  8,   3,   45.00, $vendorIds['TechSupply Co.'], 'HP 26X toner cartridge',            'active'],
    ['USB-C Hub',           'INV-003', 'Accessories',  15,  5,   25.00, $vendorIds['TechSupply Co.'], 'Multi-port USB-C hub',              'active'],
    ['Ethernet Cable 10m',  'INV-004', 'Networking',   20,  5,   12.00, $vendorIds['NetConnect'],     'Cat6 Ethernet cable 10 meter',      'active'],
    ['Office Chair',        'INV-005', 'Furniture',    10,  2,   150.00,$vendorIds['OfficeMart'],     'Ergonomic office chair',            'active'],
    ['Mouse Wireless',      'INV-006', 'Accessories',  25,  10,  15.00, $vendorIds['TechSupply Co.'], 'Logitech wireless mouse',           'active'],
    ['Keyboard USB',        'INV-007', 'Accessories',  20,  5,   20.00, $vendorIds['TechSupply Co.'], 'Standard USB keyboard',             'active'],
    ['Whiteboard Markers',  'INV-008', 'Stationery',   30,  10,  2.00,  $vendorIds['OfficeMart'],     'Pack of 4 assorted colors',        'active'],
];
$invItemIds = [];
foreach ($invItemsData as $ii) {
    $id = insert($db, 'inventory_items', [
        'item_name'   => $ii[0],
        'item_code'   => $ii[1],
        'category'    => $ii[2],
        'quantity'    => $ii[3],
        'min_quantity'=> $ii[4],
        'unit_price'  => $ii[5],
        'vendor_id'   => $ii[6],
        'description' => $ii[7],
        'status'      => $ii[8],
    ]);
    $invItemIds[$ii[0]] = $id;
}
echo "24. Inventory Items: " . count($invItemsData) . " created\n";

// ---------------------------------------------------------------
// 25. INVENTORY LOGS
// ---------------------------------------------------------------
$invLogs = [
    ['A4 Paper Ream',      'in',  100, 'PO-001', 'Initial stock',        1],
    ['A4 Paper Ream',      'out', 50,  'REQ-001', 'Department issue',    1],
    ['Toner Cartridge HP', 'in',  20,  'PO-002', 'Bulk purchase',       1],
    ['Toner Cartridge HP', 'out', 12,  'REQ-002', 'Printer replacement', 1],
    ['USB-C Hub',          'in',  30,  'PO-003', 'Stock replenishment',  1],
    ['USB-C Hub',          'out', 15,  'REQ-003', 'New hires setup',     1],
    ['Office Chair',       'in',  15,  'PO-004', 'Furniture order',      1],
    ['Office Chair',       'out', 5,   'REQ-004', 'Workstation setup',   1],
    ['Mouse Wireless',     'in',  50,  'PO-005', 'Bulk mouse purchase',  1],
    ['Mouse Wireless',     'out', 25,  'REQ-005', 'Team replacement',    1],
];
foreach ($invLogs as $il) {
    insert($db, 'inventory_logs', [
        'item_id'          => $invItemIds[$il[0]],
        'movement_type'    => $il[1],
        'quantity'         => $il[2],
        'reference_number' => $il[3],
        'notes'            => $il[4],
        'created_by'       => $il[5],
    ]);
}
echo "25. Inventory Logs: " . count($invLogs) . " created\n";

// ---------------------------------------------------------------
// 26. TICKETS
// ---------------------------------------------------------------
$ticketCat = idMap($db, 'ticket_categories', 'category_name');
$slaMap = idMap($db, 'slas', 'sla_name');

$ticketsData = [
    ['TKT-' . date('Ym') . '0001', $empId[1], $ticketCat['Technical Issue'],   $slaMap['High Priority SLA'],    'Email not sending from Outlook',  'Outlook client shows send error since morning.',                                                                  'high',    'in_progress', 'web',    $empId[2], '2026-05-28 09:15:00', null, null],
    ['TKT-' . date('Ym') . '0002', $empId[2], $ticketCat['Server Issue'],       $slaMap['Critical SLA'],         'ERP server slow response',         'Server taking >10s to respond during peak hours.',                                                                 'critical','open',        'internal',$empId[0], '2026-06-01 14:30:00', '2026-06-01 15:00:00', null],
    ['TKT-' . date('Ym') . '0003', $empId[4], $ticketCat['Billing'],            $slaMap['Medium Priority SLA'],  'Invoice payment discrepancy',      'Payment received but not reflecting in invoice INV-2026-005.',                                                    'medium',  'open',        'email',   $empId[2], '2026-06-02 11:00:00', null, null],
    ['TKT-' . date('Ym') . '0004', $empId[5], $ticketCat['Access Request'],     $slaMap['High Priority SLA'],    'New hire access request',          'Need CRM and email access for new marketing executive.',                                                           'medium',  'resolved',    'web',    $empId[2], '2026-05-25 10:00:00', '2026-05-25 11:00:00', '2026-05-26 15:30:00'],
    ['TKT-' . date('Ym') . '0005', $empId[7], $ticketCat['Hardware Problem'],   $slaMap['High Priority SLA'],    'Keyboard not working',             'Logitech MX Keys stopped responding intermittently.',                                                              'low',     'resolved',    'phone',  $empId[0], '2026-05-20 16:45:00', '2026-05-20 17:00:00', '2026-05-22 10:00:00'],
];
$ticketIds = [];
foreach ($ticketsData as $tkt) {
    $id = insert($db, 'tickets', [
        'ticket_number'    => $tkt[0],
        'employee_id'      => $tkt[1],
        'category_id'      => $tkt[2],
        'sla_id'           => $tkt[3],
        'subject'          => $tkt[4],
        'description'      => $tkt[5],
        'priority'         => $tkt[6],
        'status'           => $tkt[7],
        'source'           => $tkt[8],
        'assigned_to'      => $tkt[9],
        'created_at'       => $tkt[10],
        'first_response_at'=> $tkt[11],
        'resolved_at'      => $tkt[12],
    ]);
    $ticketIds[$tkt[0]] = $id;
}
echo "26. Tickets: " . count($ticketsData) . " created\n";

// ---------------------------------------------------------------
// 27. TICKET REPLIES
// ---------------------------------------------------------------
// ticket_replies.replied_by references users.id, not employees.id
$adminUserId = 1; // Super Admin from schema.sql
$ticketReplies = [
    ['TKT-' . date('Ym') . '0001', $adminUserId, 'public',  'Received the ticket. We are looking into this.',                                                       '2026-05-28 10:00:00'],
    ['TKT-' . date('Ym') . '0001', $adminUserId, 'public',  'Checking the Outlook configuration now.',                                                              '2026-05-28 10:30:00'],
    ['TKT-' . date('Ym') . '0001', $adminUserId, 'internal','This might be an SSL certificate issue on the mail server.',                                           '2026-05-28 11:00:00'],
    ['TKT-' . date('Ym') . '0002', $adminUserId, 'public',  'Investigating server load. Will provide update in 2 hours.',                                           '2026-06-01 15:30:00'],
    ['TKT-' . date('Ym') . '0003', $adminUserId, 'public',  'I can see the payment. Updating the invoice now.',                                                     '2026-06-02 14:00:00'],
    ['TKT-' . date('Ym') . '0004', $adminUserId, 'public',  'Access has been granted. Credentials shared via email.',                                                '2026-05-25 14:00:00'],
    ['TKT-' . date('Ym') . '0005', $adminUserId, 'public',  'Please bring the keyboard to IT Helpdesk for replacement.',                                            '2026-05-21 09:00:00'],
    ['TKT-' . date('Ym') . '0005', $adminUserId, 'public',  'Replaced the keyboard with a new one. Working fine now.',                                              '2026-05-22 09:30:00'],
];
foreach ($ticketReplies as $tr) {
    insert($db, 'ticket_replies', [
        'ticket_id'   => $ticketIds[$tr[0]],
        'replied_by'  => $tr[1],
        'reply_type'  => $tr[2],
        'message'     => $tr[3],
        'created_at'  => $tr[4],
    ]);
}
echo "27. Ticket Replies: " . count($ticketReplies) . " created\n";

// ---------------------------------------------------------------
// 28. KNOWLEDGE BASE ARTICLES
// ---------------------------------------------------------------
$kbCat = idMap($db, 'knowledge_base_categories', 'name');
$kbArticles = [
    [$kbCat['Getting Started'],    'How to Set Up Email Signature',  'email-signature-setup',    '<p>Follow these steps to set up your email signature in Outlook:</p><ol><li>Open Outlook</li><li>Go to File &gt; Options &gt; Mail</li><li>Click Signatures</li><li>Create new signature</li></ol>',                       'email,outlook,setup',      'published', 1, 45],
    [$kbCat['Getting Started'],    'Requesting New Software',        'request-new-software',     '<p>To request new software for your workstation, create a helpdesk ticket with the "Access Request" category.</p>',                                                            'software,access,request',  'published', 1, 32],
    [$kbCat['How-To Guides'],      'Leave Application Process',      'leave-application',        '<p>Go to Attendance > Leave Requests, click Apply Leave, select type and dates, and submit for approval.</p>',                                                                  'leave,hr,attendance',      'published', 1, 78],
    [$kbCat['Troubleshooting'],    'VPN Connection Guide',            'vpn-connection-guide',     '<p>Download the VPN client, install, enter credentials, select server, and connect.</p>',                                                                                         'vpn,security,remote',      'published', 1, 56],
    [$kbCat['FAQ'],                'Password Change Policy',          'password-change',          '<p>Minimum 8 characters, one uppercase, one number, one special char. Change every 90 days.</p>',                                                                                'password,security',        'published', 1, 120],
];
foreach ($kbArticles as $kb) {
    insert($db, 'knowledge_base_articles', [
        'category_id' => $kb[0],
        'title'       => $kb[1],
        'slug'        => $kb[2],
        'content'     => $kb[3],
        'tags'        => $kb[4],
        'status'      => $kb[5],
        'created_by'  => $kb[6],
        'views'       => $kb[7],
    ]);
}
echo "28. Knowledge Base Articles: " . count($kbArticles) . " created\n";

// ---------------------------------------------------------------
// 29. AUDIT LOGS
// ---------------------------------------------------------------
$auditLogs = [
    [1, 'login',    'auth',     '192.168.1.100', '2026-06-05 09:00:00'],
    [1, 'create',   'employees','192.168.1.100', '2026-06-05 09:05:00'],
    [1, 'view',     'reports',  '192.168.1.100', '2026-06-05 09:15:00'],
    [1, 'login',    'auth',     '192.168.1.101', '2026-06-04 10:00:00'],
    [1, 'edit',     'projects', '192.168.1.101', '2026-06-04 10:30:00'],
    [1, 'create',   'tickets',  '192.168.1.100', '2026-06-02 11:00:00'],
    [1, 'login',    'auth',     '192.168.1.100', '2026-06-02 08:30:00'],
    [1, 'approve',  'expenses', '192.168.1.100', '2026-06-01 16:00:00'],
];
foreach ($auditLogs as $al) {
    insert($db, 'audit_logs', [
        'user_id'    => $al[0],
        'action'     => $al[1],
        'module'     => $al[2],
        'ip_address' => $al[3],
        'created_at' => $al[4],
    ]);
}
echo "29. Audit Logs: " . count($auditLogs) . " created\n";

// ---------------------------------------------------------------
// Summary
// ---------------------------------------------------------------
echo "\n=== SAMPLE DATA IMPORT COMPLETE ===\n\n";

$tables = ['designations','employees','leave_balances','attendance','leave_requests','projects',
           'project_members','milestones','tasks','task_comments','timesheets','leads','clients',
           'deals','invoices','invoice_items','payments','expenses','vendors','assets',
           'asset_assignments','asset_maintenance','software_licenses','inventory_items',
           'inventory_logs','tickets','ticket_replies','knowledge_base_articles','audit_logs'];
foreach ($tables as $t) {
    $c = $db->query("SELECT COUNT(*) as c FROM $t")->fetch()['c'];
    echo str_pad($t, 22) . " $c rows\n";
}
