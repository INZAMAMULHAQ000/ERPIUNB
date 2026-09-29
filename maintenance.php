<?php

/**
 * ============================================================
 * ERP MAINTENANCE CONSOLE — clean & seed module data
 * ============================================================
 *
 * SAFETY GUARANTEES:
 *   • users.id = 1 is NEVER deleted
 *   • roles.id = 1 is NEVER deleted
 *   • user_roles for Super Admin is NEVER deleted
 *   • permissions + role_permissions are NEVER wiped
 *   • sidebar_menu is NEVER wiped
 *   • companies.id = 1 is NEVER deleted
 *   • settings / *_settings singleton rows preserved
 *
 * CLI:
 *   php maintenance.php clean users
 *   php maintenance.php seed core
 *   php maintenance.php clean procurement
 *   php maintenance.php seed procurement
 *   php maintenance.php seed finance_demo
 * ============================================================
 */

$isCli = php_sapi_name() === 'cli';

if (!$isCli && session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/finance_helpers.php';


/* ============================================================
   AUDIT LOG FALLBACK
============================================================ */

if (!function_exists('logAudit')) {
    function logAudit($u, $a, $m)
    {
        $d = getDB();

        try {
            $d->prepare("
                INSERT INTO audit_logs
                (
                    user_id,
                    action,
                    module,
                    ip_address
                )
                VALUES (?, ?, ?, ?)
            ")->execute([
                $u,
                $a,
                $m,
                '127.0.0.1'
            ]);
        } catch (Exception $e) {
            // Audit failure must not break maintenance operation.
        }
    }
}


$_SESSION = $_SESSION ?? [];


/* ============================================================
   WEB ACCESS — SUPER ADMIN ONLY
============================================================ */

if (!$isCli) {

    $uid = (int)($_SESSION['user_id'] ?? 0);
    $ok  = false;

    if ($uid) {

        try {

            $db = getDB();

            $stmt = $db->prepare("
                SELECT COUNT(*)
                FROM user_roles ur
                INNER JOIN roles r
                    ON r.id = ur.role_id
                WHERE ur.user_id = ?
                  AND r.id = 1
            ");

            $stmt->execute([$uid]);

            $ok = (bool)$stmt->fetchColumn();
        } catch (Exception $e) {

            $ok = false;
        }
    }

    if (!$ok) {

        http_response_code(403);

        die('
            <div style="
                font-family:Arial,sans-serif;
                padding:40px;
                text-align:center;
            ">
                <h2 style="color:#b91c1c;">
                    403 — Super Admin login required.
                </h2>

                <p style="color:#555;">
                    Please login as Super Admin and reopen this page.
                </p>

                <a
                    href="/XXX/modules/auth/login.php"
                    style="
                        display:inline-block;
                        margin-top:10px;
                        padding:8px 16px;
                        background:#2563eb;
                        color:#fff;
                        text-decoration:none;
                    "
                >
                    Login
                </a>
            </div>
        ');
    }
}


$db = getDB();


/* ============================================================
   PROTECTED RECORDS
============================================================ */

const PROTECT_USER_IDS = [1];

const PROTECT_ROLE_IDS = [1];

const PROTECT_COMPANY_IDS = [1];

const PROTECTED_SUMMARY = [
    'users id=1 (your login)',
    'roles id=1 (Super Admin)',
    'user_roles for Super Admin',
    'permissions & role_permissions',
    'sidebar_menu',
    'companies id=1',
    'all *_settings tables',
    'chart_of_accounts',
    'financial_years of company 1',
];


/* ============================================================
   MODULE REGISTRY
============================================================ */

$MODULES = [

    /* ========================================================
       USERS
    ======================================================== */

    'users' => [

        'label' => 'Users & Access',

        'detail' =>
        'CLEAN removes all users except id=1 + their role/company links. SEED creates 6 demo staff.',

        'protect' =>
        'users.id=1, roles, permissions',

        'clean' => function ($db) {

            foreach (
                ['user_companies', 'user_roles'] as $t
            ) {

                $db->exec("
                    DELETE FROM $t
                    WHERE user_id NOT IN (
                        " . implode(',', PROTECT_USER_IDS) . "
                    )
                ");
            }

            return $db->exec("
                DELETE FROM users
                WHERE id NOT IN (
                    " . implode(',', PROTECT_USER_IDS) . "
                )
            ") . " users removed";
        },

        'seed' => function ($db) {

            $n = 0;

            $users = [

                [
                    'Sara Haddad',
                    'sara@erp.test',
                    'manager',
                    2
                ],

                [
                    'Omar Farouk',
                    'omar@erp.test',
                    'requester',
                    2
                ],

                [
                    'Lina Habib',
                    'lina@erp.test',
                    'finance',
                    2
                ],

                [
                    'Yusuf Karim',
                    'yusuf@erp.test',
                    'employee',
                    3
                ],

                [
                    'Mona Aziz',
                    'mona@erp.test',
                    'approver',
                    2
                ],

                [
                    'Rashid Ali',
                    'rashid@erp.test',
                    'it',
                    2
                ],
            ];

            $iu = $db->prepare("
                INSERT INTO users
                (
                    name,
                    email,
                    password,
                    status
                )
                VALUES (?, ?, ?, 'active')

                ON DUPLICATE KEY UPDATE
                    name = VALUES(name)
            ");

            $hash = password_hash(
                'demo123',
                PASSWORD_DEFAULT
            );

            $iur = $db->prepare("
                INSERT IGNORE INTO user_roles
                (
                    user_id,
                    role_id
                )
                VALUES (?, ?)
            ");

            $iuc = $db->prepare("
                INSERT IGNORE INTO user_companies
                (
                    user_id,
                    company_id
                )
                SELECT ?, 1
            ");

            foreach ($users as $u) {

                $iu->execute([
                    $u[0],
                    $u[1],
                    $hash
                ]);

                $stmt = $db->prepare("
                    SELECT id
                    FROM users
                    WHERE email = ?
                    LIMIT 1
                ");

                $stmt->execute([$u[1]]);

                $id = (int)$stmt->fetchColumn();

                if ($id) {

                    $iur->execute([
                        $id,
                        $u[3]
                    ]);

                    $iuc->execute([
                        $id
                    ]);

                    $n++;
                }
            }

            return "$n demo users created (password: demo123)";
        },
    ],


    /* ========================================================
       COMPANIES
    ======================================================== */

    'companies_group' => [

        'label' =>
        'Companies & Group Structure',

        'detail' =>
        'CLEAN removes every company except id=1. SEED creates a group + 2 subsidiaries.',

        'protect' =>
        'companies.id=1',

        'clean' => function ($db) {

            $stmt = $db->query("
                SELECT GROUP_CONCAT(id)
                FROM companies
                WHERE id NOT IN (
                    " . implode(',', PROTECT_COMPANY_IDS) . "
                )
            ");

            $ids = $stmt->fetchColumn();

            if (!$ids) {
                return 'nothing to remove';
            }

            $in = "IN ($ids)";

            tq(
                $db,
                "
                DELETE crc
                FROM consolidation_run_companies crc
                JOIN consolidation_runs r
                    ON r.id = crc.run_id
                WHERE crc.company_id $in
                  AND r.status <> 'posted'
                "
            );

            tq(
                $db,
                "
                DELETE FROM intercompany_transactions
                WHERE source_company_id $in
                   OR target_company_id $in
                "
            );

            tq(
                $db,
                "
                DELETE FROM intercompany_accounts
                WHERE company_id $in
                "
            );

            tq(
                $db,
                "
                DELETE FROM company_relationships
                WHERE child_company_id $in
                   OR parent_company_id $in
                "
            );

            foreach (
                [
                    'consolidation_journals',
                    'consolidation_run_companies'
                ] as $t
            ) {

                tq(
                    $db,
                    "
                    DELETE x
                    FROM $t x
                    JOIN consolidation_runs r
                        ON r.id = x.run_id
                    LEFT JOIN consolidation_run_companies rc
                        ON rc.run_id = r.id
                    WHERE rc.company_id IS NULL
                       OR rc.company_id NOT IN (
                            SELECT id
                            FROM companies
                       )
                    "
                );
            }

            tq(
                $db,
                "
                DELETE FROM consolidation_runs
                WHERE id NOT IN (
                    SELECT DISTINCT run_id
                    FROM consolidation_run_companies
                )
                "
            );

            tq(
                $db,
                "
                DELETE g
                FROM company_groups g
                LEFT JOIN companies c
                    ON c.id = g.parent_company_id
                WHERE c.id IS NULL
                "
            );

            return $db->exec("
                DELETE FROM companies
                WHERE id $in
            ") . " companies removed";
        },

        'seed' => function ($db) {

            $n = 0;

            $db->exec("
                INSERT INTO company_groups
                (
                    group_code,
                    group_name,
                    parent_company_id,
                    base_currency,
                    status
                )

                SELECT
                    'GRP-MAIN',
                    'Main Holding Group',
                    1,
                    'AED',
                    'active'

                WHERE NOT EXISTS (
                    SELECT 1
                    FROM company_groups
                    WHERE group_code = 'GRP-MAIN'
                )
            ");

            $gid = (int)$db->query("
                SELECT id
                FROM company_groups
                WHERE group_code = 'GRP-MAIN'
                LIMIT 1
            ")->fetchColumn();

            $subs = [

                [
                    'SUB-Tech',
                    'Nexora Technologies FZE',
                    'Tech trading & services',
                    'AED',
                    'AE'
                ],

                [
                    'SUB-Retail',
                    'Nexora Retail LLC',
                    'Retail operations',
                    'AED',
                    'AE'
                ],
            ];

            foreach ($subs as $s) {

                $stmt = $db->prepare("
                    INSERT INTO companies
                    (
                        company_code,
                        company_name,
                        legal_name,
                        registration_number,
                        tax_number,
                        base_currency,
                        country,
                        timezone,
                        status
                    )

                    SELECT
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        'Asia/Dubai',
                        'active'

                    WHERE NOT EXISTS (
                        SELECT 1
                        FROM companies
                        WHERE company_code = ?
                    )
                ");

                $stmt->execute([
                    $s[0],
                    $s[1],
                    $s[2],
                    'CN-' . $s[0],
                    'TRN-100' . $n,
                    $s[3],
                    $s[4],
                    $s[0]
                ]);

                $stmt = $db->prepare("
                    SELECT id
                    FROM companies
                    WHERE company_code = ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $s[0]
                ]);

                $cid = (int)$stmt->fetchColumn();

                if ($cid) {

                    $stmt = $db->prepare("
                        INSERT INTO company_relationships
                        (
                            group_id,
                            parent_company_id,
                            child_company_id,
                            ownership_percentage,
                            effective_from,
                            relationship_type,
                            status
                        )

                        SELECT
                            ?,
                            1,
                            ?,
                            100,
                            CURDATE(),
                            'subsidiary',
                            'active'

                        WHERE NOT EXISTS (
                            SELECT 1
                            FROM company_relationships
                            WHERE child_company_id = ?
                              AND status = 'active'
                        )
                    ");

                    $stmt->execute([
                        $gid,
                        $cid,
                        $cid
                    ]);

                    $n++;
                }
            }

            return "Group + $n subsidiaries ready";
        },
    ],


    /* ========================================================
       DEPARTMENTS
    ======================================================== */

    'departments' => [

        'label' => 'Departments',

        'detail' =>
        'CLEAN removes all departments. SEED creates 8 typical departments.',

        'clean' => function ($db) {

            return $db->exec("
                DELETE FROM departments
            ") . " departments removed";
        },

        'seed' => function ($db) {

            $list = [
                'Information Technology',
                'Finance & Accounts',
                'Human Resources',
                'Operations',
                'Sales & Marketing',
                'Procurement',
                'Facilities',
                'Legal'
            ];

            $ins = $db->prepare("
                INSERT INTO departments
                (
                    department_name,
                    department_code,
                    status
                )
                VALUES (?, ?, 'active')
            ");

            $n = 0;

            foreach ($list as $d) {

                $code =
                    strtoupper(
                        substr(
                            preg_replace(
                                '/[^A-Za-z]/',
                                '',
                                explode(' ', $d)[0]
                            ),
                            0,
                            3
                        )
                    )
                    .
                    str_pad(
                        ++$n,
                        2,
                        '0',
                        STR_PAD_LEFT
                    );

                $ins->execute([
                    $d,
                    $code
                ]);
            }

            return count($list) . " departments created";
        },
    ],


    /* ========================================================
       PROJECTS
    ======================================================== */

    'projects' => [

        'label' => 'Projects',

        'detail' =>
        'CLEAN removes all projects. SEED creates 5 active demo projects.',

        'clean' => function ($db) {

            return $db->exec("
                DELETE FROM projects
            ") . " projects removed";
        },

        'seed' => function ($db) {

            $list = [

                [
                    'PRJ-DIGI',
                    'Digital Transformation',
                    'Internal'
                ],

                [
                    'PRJ-FITOUT',
                    'Office Fit-out 2026',
                    'Internal'
                ],

                [
                    'PRJ-ERPUP',
                    'ERP Upgrade Phase 2',
                    'Client: Self'
                ],

                [
                    'PRJ-CRMROL',
                    'CRM Rollout',
                    'Client: Nexora Retail'
                ],

                [
                    'PRJ-ISO27001',
                    'ISO 27001 Certification',
                    'Internal'
                ],
            ];

            $ins = $db->prepare("
                INSERT INTO projects
                (
                    project_code,
                    project_name,
                    client_name,
                    status,
                    start_date
                )
                VALUES (?, ?, ?, 'active', CURDATE())
            ");

            foreach ($list as $p) {
                $ins->execute($p);
            }

            return count($list) . " projects created";
        },
    ],


    /* ========================================================
       COST CENTERS
    ======================================================== */

    'cost_centers' => [

        'label' => 'Cost Centers',

        'detail' =>
        'CLEAN removes cost centers. SEED adds 6 standard centers.',

        'clean' => function ($db) {

            return $db->exec("
                DELETE FROM cost_centers
                WHERE cost_center_code <> 'CC-ADMIN'
            ") . " cost centers removed (CC-ADMIN kept)";
        },

        'seed' => function ($db) {

            $list = [
                'CC-SALES' => 'Sales & BD',
                'CC-MKTG' => 'Marketing',
                'CC-ITINF' => 'IT Infrastructure',
                'CC-HRADM' => 'HR & Admin',
                'CC-OPS' => 'Operations',
                'CC-FIN' => 'Finance'
            ];

            $ins = $db->prepare("
                INSERT INTO cost_centers
                (
                    company_id,
                    cost_center_code,
                    cost_center_name,
                    status
                )

                SELECT
                    1,
                    ?,
                    ?,
                    'active'

                WHERE NOT EXISTS (
                    SELECT 1
                    FROM cost_centers
                    WHERE cost_center_code = ?
                )
            ");

            foreach ($list as $k => $v) {

                $ins->execute([
                    $k,
                    $v,
                    $k
                ]);
            }

            return count($list) . " cost centers ensured";
        },
    ],


    /* ========================================================
       VENDORS
    ======================================================== */

    'vendors' => [

        'label' => 'Vendors / Suppliers',

        'detail' =>
        'CLEAN removes all vendors. SEED creates 10 UAE vendors.',

        'clean' => function ($db) {

            return $db->exec("
                DELETE FROM vendors
            ") . " vendors removed";
        },

        'seed' => function ($db) {

            $v = [

                [
                    'V-ALTECH',
                    'Al Tech Computers LLC',
                    'IT hardware',
                    'VAT-100234'
                ],

                [
                    'V-OFFICEX',
                    'OfficeX Trading',
                    'Office supplies',
                    'VAT-100235'
                ],

                [
                    'V-GULFSERV',
                    'Gulf Services Co',
                    'Facilities management',
                    'VAT-100236'
                ],

                [
                    'V-NETPRO',
                    'NetPro Networks',
                    'Networking & cabling',
                    'VAT-100237'
                ],

                [
                    'V-CREATIV',
                    'Creative Studio FZE',
                    'Marketing & design',
                    'VAT-100238'
                ],

                [
                    'V-LOGISTIQ',
                    'LogistiQ Movers',
                    'Logistics',
                    'VAT-100239'
                ],

                [
                    'V-SECURI',
                    'SecuriPlus Systems',
                    'CCTV & access control',
                    'VAT-100240'
                ],

                [
                    'V-FURNISH',
                    'Furnish Interiors',
                    'Furniture & fitout',
                    'VAT-100241'
                ],

                [
                    'V-CLOUDAE',
                    'Cloud AE Solutions',
                    'Software subscriptions',
                    'VAT-100242'
                ],

                [
                    'V-MAINTCO',
                    'MaintCo Technical',
                    'AC & electrical maintenance',
                    'VAT-100243'
                ],
            ];

            $ins = $db->prepare("
                INSERT INTO vendors
                (
                    vendor_code,
                    vendor_name,
                    notes,
                    vat_number,
                    currency,
                    payment_terms,
                    status
                )

                SELECT
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'Net 30',
                    'active'

                WHERE NOT EXISTS (
                    SELECT 1
                    FROM vendors
                    WHERE vendor_code = ?
                )
            ");

            foreach ($v as $x) {

                $ins->execute([
                    $x[0],
                    $x[1],
                    $x[2],
                    $x[3],
                    'AED',
                    $x[0]
                ]);
            }

            return count($v) . " vendors ensured";
        },
    ],


    /* ========================================================
       TAX
    ======================================================== */

    'tax_rates' => [

        'label' => 'Tax Types (UAE VAT)',

        'detail' =>
        'SEED ensures standard UAE tax types. CLEAN empties the table first.',

        'clean' => function ($db) {

            return $db->exec("
                DELETE FROM tax_types
            ") . " tax types removed";
        },

        'seed' => function ($db) {

            $ins = $db->prepare("
                INSERT INTO tax_types
                (
                    name,
                    code,
                    description,
                    status
                )

                SELECT
                    ?,
                    ?,
                    ?,
                    'active'

                WHERE NOT EXISTS (
                    SELECT 1
                    FROM tax_types
                    WHERE code = ?
                )
            ");

            $taxes = [

                [
                    'VAT 5%',
                    'VAT5',
                    'Standard UAE VAT 5%'
                ],

                [
                    'Zero-rated',
                    'ZERORATED',
                    'Zero-rated supplies'
                ],

                [
                    'Exempt',
                    'EXEMPT',
                    'Exempt supplies'
                ],
            ];

            foreach ($taxes as $t) {

                $ins->execute([
                    $t[0],
                    $t[1],
                    $t[2],
                    $t[1]
                ]);
            }

            return "UAE tax types ensured";
        },
    ],


    /* ========================================================
       FX
    ======================================================== */

    'fx_rates' => [

        'label' => 'Exchange Rates',

        'detail' =>
        'SEED restores USD/EUR→AED closing & average rates. CLEAN empties them.',

        'clean' => function ($db) {

            return $db->exec("
                DELETE FROM exchange_rates
            ") . " rates removed";
        },

        'seed' => function ($db) {

            $ins = $db->prepare("
                INSERT IGNORE INTO exchange_rates
                (
                    from_currency,
                    to_currency,
                    rate,
                    rate_type,
                    effective_date
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $rates = [

                [
                    'USD',
                    'AED',
                    3.6725,
                    'closing',
                    '2030-12-31'
                ],

                [
                    'USD',
                    'AED',
                    3.673,
                    'average',
                    '2030-01-01'
                ],

                [
                    'EUR',
                    'AED',
                    4.05,
                    'closing',
                    '2030-12-31'
                ],

                [
                    'EUR',
                    'AED',
                    4.045,
                    'average',
                    '2030-01-01'
                ],

                [
                    'GBP',
                    'AED',
                    4.65,
                    'closing',
                    '2030-12-31'
                ],

                [
                    'SAR',
                    'AED',
                    0.9789,
                    'closing',
                    '2030-12-31'
                ],
            ];

            foreach ($rates as $r) {
                $ins->execute($r);
            }

            return "Standard FX rates ensured";
        },
    ],


    /* ========================================================
       PROCUREMENT
    ======================================================== */

    'procurement' => [

        'label' =>
        'Purchase Requisitions (P1)',

        'detail' =>
        'CLEAN removes ALL requisitions + approvals + attachments + audit. SEED creates 8 PRs across every workflow status with realistic items.',

        'depends' =>
        'departments, companies, vendors',

        'clean' => function ($db) {

            foreach (
                [
                    'purchase_requisition_attachments',
                    'purchase_requisition_approvals',
                    'purchase_requisition_items',
                    'purchase_requisitions'
                ] as $t
            ) {

                $db->exec("
                    DELETE FROM $t
                ");
            }

            $db->exec("
                DELETE FROM procurement_audit
            ");

            return 'All requisition data cleared';
        },

        'seed' => function ($db) {

            require_once __DIR__ .
                '/modules/procurement/ProcurementService.php';

            $n = (int)$db->query("
                SELECT COUNT(*)
                FROM purchase_requisitions
            ")->fetchColumn();

            if ($n > 0) {
                return "Skipped — $n PRs already exist (run CLEAN first)";
            }

            $coIds = $db->query("
                SELECT id
                FROM companies
                ORDER BY id
                LIMIT 2
            ")->fetchAll(PDO::FETCH_COLUMN);

            $depts = $db->query("
                SELECT id
                FROM departments
                WHERE status = 'active'
                LIMIT 4
            ")->fetchAll(PDO::FETCH_COLUMN);

            $projs = $db->query("
                SELECT id
                FROM projects
                WHERE status = 'active'
                LIMIT 3
            ")->fetchAll(PDO::FETCH_COLUMN);

            $ccs = $db->query("
                SELECT id
                FROM cost_centers
                WHERE status = 'active'
                LIMIT 3
            ")->fetchAll(PDO::FETCH_COLUMN);

            $vend = $db->query("
                SELECT id
                FROM vendors
                WHERE status = 'active'
                LIMIT 5
            ")->fetchAll(PDO::FETCH_COLUMN);

            $reqUid = (int)(
                $db->query("
                    SELECT id
                    FROM users
                    WHERE email = 'omar@erp.test'
                       OR email = 'sara@erp.test'
                    ORDER BY id
                    LIMIT 1
                ")->fetchColumn()
                ?: 1
            );

            $aprUid = (int)(
                $db->query("
                    SELECT id
                    FROM users
                    WHERE email = 'mona@erp.test'
                       OR id = 1
                    ORDER BY
                        (email = 'mona@erp.test') DESC
                    LIMIT 1
                ")->fetchColumn()
                ?: 1
            );

            if (!count($depts)) {
                return 'Seed Departments first';
            }

            if (!count($coIds)) {
                return 'Company missing?!';
            }

            $deptId = fn() =>
            $depts[array_rand($depts)];

            $plans = [

                [
                    'Workstation refresh',
                    'asset',
                    'Laptop 16"',
                    2,
                    7500,
                    5,
                    'high'
                ],

                [
                    'Printer paper & toner restock',
                    'non_stock',
                    'A4 paper carton',
                    40,
                    45,
                    0,
                    'normal'
                ],

                [
                    'Annual software licences',
                    'service',
                    'MS 365 Business seats',
                    25,
                    55,
                    0,
                    'normal'
                ],

                [
                    'Meeting room AV upgrade',
                    'asset',
                    '65" display + mount',
                    2,
                    4200,
                    5,
                    'normal'
                ],

                [
                    'Server rack & UPS',
                    'asset',
                    '42U rack with PDU',
                    1,
                    8500,
                    5,
                    'urgent'
                ],

                [
                    'Team building offsite',
                    'expense',
                    'Desert resort day package',
                    30,
                    180,
                    0,
                    'low'
                ],

                [
                    'Cabling & network points',
                    'service',
                    'Cat6 point installation',
                    12,
                    150,
                    5,
                    'normal'
                ],

                [
                    'Ergonomic chairs',
                    'non_stock',
                    'Mesh ergonomic chair',
                    6,
                    1250,
                    5,
                    'high'
                ],
            ];

            $statuses = [
                'draft',
                'submitted',
                'pending_approval',
                'approved',
                'approved',
                'rejected',
                'cancelled',
                'pending_approval'
            ];

            $made = 0;

            foreach ($plans as $i => $pl) {

                $status =
                    $statuses[$i % count($statuses)];

                $hdr = [

                    'company_id' =>
                    $coIds[$i % count($coIds)],

                    'department_id' =>
                    $deptId(),

                    'project_id' =>
                    $projs[$i % max(1, count($projs))] ?? null,

                    'cost_center_id' =>
                    $ccs[$i % max(1, count($ccs))] ?? null,

                    'branch' => 'HQ',

                    'requester_id' =>
                    $reqUid,

                    'request_date' =>
                    date(
                        'Y-m-d',
                        strtotime("-" . (10 - $i) . " days")
                    ),

                    'required_date' =>
                    date(
                        'Y-m-d',
                        strtotime("+" . (15 + $i) . " days")
                    ),

                    'priority' => $pl[6],

                    'purpose' => $pl[0],

                    'description' =>
                    'Seeded demo requisition',

                    'currency' => 'AED',
                ];

                $line = [

                    'item_type' =>
                    $pl[1],

                    'item_name' =>
                    $pl[2],

                    'quantity' =>
                    $pl[3],

                    'unit' =>
                    $pl[1] === 'service'
                        ? 'hours'
                        : 'pcs',

                    'est_unit_price' =>
                    $pl[4],

                    'discount_amount' =>
                    0,

                    'tax_percent' =>
                    $pl[5],

                    'preferred_vendor_id' =>
                    count($vend)
                        ? $vend[$i % count($vend)]
                        : null,

                    'department_id' =>
                    $deptId(),
                ];

                $r = ProcurementService::createPR(
                    $db,
                    $hdr,
                    [$line],
                    $reqUid
                );

                if (empty($r['success'])) {
                    continue;
                }

                $prId = (int)$r['id'];

                if (
                    in_array(
                        $status,
                        [
                            'submitted',
                            'pending_approval',
                            'approved',
                            'rejected'
                        ],
                        true
                    )
                ) {

                    ProcurementService::submit(
                        $db,
                        $prId,
                        $reqUid
                    );

                    if ($status === 'approved') {

                        ProcurementService::approve(
                            $db,
                            $prId,
                            $aprUid,
                            'Budget available'
                        );
                    }

                    if ($status === 'rejected') {

                        ProcurementService::reject(
                            $db,
                            $prId,
                            $aprUid,
                            'Not in this quarter plan'
                        );
                    }
                }

                if ($status === 'cancelled') {

                    ProcurementService::cancel(
                        $db,
                        $prId,
                        $reqUid,
                        'No longer required'
                    );
                }

                $made++;
            }

            return
                "$made requisitions seeded across draft/pending/approved/rejected/cancelled";
        },
    ],


    /* ========================================================
       FINANCE DEMO
    ======================================================== */

    'finance_demo' => [

        'label' =>
        'Finance Demo Journals (safe)',

        'detail' =>
        'SEED posts 6 balanced sample journals into the ACTIVE financial year of company 1 via the Posting Engine.',

        'clean' => function ($db) {

            $fy = (int)$db->query("
                SELECT id
                FROM financial_years
                WHERE status = 'active'
                  AND company_id = 1
                ORDER BY id
                LIMIT 1
            ")->fetchColumn();

            $n = 0;

            if ($fy) {

                $st = $db->prepare("
                    DELETE gl
                    FROM general_ledger gl

                    JOIN journal_entries je
                        ON je.id = gl.journal_entry_id

                    WHERE gl.financial_year_id = ?

                      AND je.description LIKE '%demo seed%'
                ");

                $st->execute([
                    $fy
                ]);

                $n += $st->rowCount();
            }

            $db->exec("
                DELETE jd
                FROM journal_entry_details jd

                JOIN journal_entries je
                    ON je.id = jd.journal_entry_id

                WHERE je.description LIKE '%demo seed%'
            ");

            $db->exec("
                DELETE FROM journal_entries
                WHERE description LIKE '%demo seed%'
            ");

            return "$n GL rows removed";
        },

        'seed' => function ($db) {

            require_once __DIR__ .
                '/modules/finance/posting_engine/PostingEngine.php';

            require_once __DIR__ .
                '/modules/finance/helpers/finance_helpers.php';

            $fyRow = $db->query("
                SELECT *
                FROM financial_years
                WHERE status = 'active'
                  AND company_id = 1
                ORDER BY id
                LIMIT 1
            ")->fetch();

            if (!$fyRow) {
                return 'No active FY for company 1';
            }

            $acct = function ($nature) use ($db) {

                $s = $db->prepare("
                    SELECT coa.id
                    FROM chart_of_accounts coa

                    JOIN account_groups g
                        ON g.id = coa.group_id

                    WHERE g.nature = ?
                      AND coa.status = 'active'

                    LIMIT 1
                ");

                $s->execute([
                    $nature
                ]);

                return (int)$s->fetchColumn();
            };

            $bank = $acct('asset');

            $rev = $acct('income');

            $exp = $acct('expense');

            if (
                !$bank ||
                !$rev ||
                !$exp
            ) {
                return 'Chart of accounts incomplete';
            }

            $vt = (int)$db->query("
                SELECT id
                FROM voucher_types
                WHERE voucher_name = 'Journal Voucher'
                LIMIT 1
            ")->fetchColumn();

            $samples = [

                [
                    'Consulting revenue — demo seed',
                    25000,
                    $rev
                ],

                [
                    'Product sales — demo seed',
                    40000,
                    $rev
                ],

                [
                    'Office rent — demo seed',
                    8000,
                    $exp
                ],

                [
                    'Utilities — demo seed',
                    2200,
                    $exp
                ],

                [
                    'Marketing spend — demo seed',
                    5500,
                    $exp
                ],

                [
                    'Software subscriptions — demo seed',
                    1800,
                    $exp
                ],
            ];

            $made = 0;

            foreach (
                $samples as $i => $smp
            ) {

                [
                    $desc,
                    $amt,
                    $acc
                ] = $smp;

                $isIncome =
                    ($acc === $rev);

                $vn =
                    'DEMO-' .
                    date('md') .
                    '-' .
                    str_pad(
                        $i + 1,
                        3,
                        '0',
                        STR_PAD_LEFT
                    ) .
                    '-' .
                    substr(
                        md5(uniqid()),
                        0,
                        4
                    );

                $stmt = $db->prepare("
                    INSERT INTO journal_entries
                    (
                        voucher_no,
                        voucher_type_id,
                        voucher_date,
                        status,
                        created_by,
                        description
                    )
                    VALUES (?, ?, ?, 'draft', 1, ?)
                ");

                $stmt->execute([
                    $vn,
                    $vt,
                    date(
                        'Y-m-d',
                        strtotime(
                            ($i + 5) . ' days ago'
                        )
                    ),
                    $desc
                ]);

                $jid =
                    (int)$db->lastInsertId();

                $li = $db->prepare("
                    INSERT INTO journal_entry_details
                    (
                        journal_entry_id,
                        account_id,
                        debit_amount,
                        credit_amount
                    )
                    VALUES (?, ?, ?, ?)
                ");

                if ($isIncome) {

                    $li->execute([
                        $jid,
                        $bank,
                        $amt,
                        0
                    ]);

                    $li->execute([
                        $jid,
                        $acc,
                        0,
                        $amt
                    ]);
                } else {

                    $li->execute([
                        $jid,
                        $acc,
                        $amt,
                        0
                    ]);

                    $li->execute([
                        $jid,
                        $bank,
                        0,
                        $amt
                    ]);
                }

                $r =
                    PostingEngine::post(
                        $jid,
                        1
                    );

                if (!empty($r['success'])) {
                    $made++;
                }
            }

            return
                "$made demo journals posted into {$fyRow['year_name']} (balanced via Posting Engine)";
        },
    ],
];


/* ============================================================
   HELPER
============================================================ */

function tq($db, $sql)
{
    try {

        $db->exec($sql);
    } catch (Exception $e) {

        // Ignore cleanup dependency errors.
    }
}


/* ============================================================
   DISPATCH — CLI OR POST
============================================================ */

$result = null;

$resultType = 'info';

$requested = null;


/* CLI */

if (
    $isCli &&
    isset(
        $argv[1],
        $argv[2]
    )
) {

    $requested = [
        'action' =>
        $argv[1],

        'module' =>
        $argv[2],
    ];
}


/* WEB */ elseif (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    $requested = [
        'action' =>
        $_POST['action'] ?? '',

        'module' =>
        $_POST['module'] ?? '',
    ];
}


/* ============================================================
   EXECUTE REQUEST
============================================================ */

if (
    $requested &&
    isset(
        $MODULES[$requested['module']]
    )
) {

    $mod =
        $MODULES[$requested['module']];

    $fn =
        $requested['action'] === 'clean'
        ? ($mod['clean'] ?? null)
        : ($mod['seed'] ?? null);

    if ($fn) {

        try {

            $result =
                call_user_func(
                    $fn,
                    $db
                );

            $resultType =
                $requested['action'];
        } catch (Exception $e) {

            $result =
                'ERROR: ' .
                $e->getMessage();

            $resultType =
                'error';
        }
    } else {

        $result =
            ucfirst(
                $requested['action']
            ) .
            ' not available for this module.';
    }
}


/* ============================================================
   CLI OUTPUT
============================================================ */

if ($isCli) {

    echo (
        $result ??
        'usage: php maintenance.php [clean|seed] [' .
        implode(
            '|',
            array_keys($MODULES)
        ) .
        ']'
    ) . PHP_EOL;

    exit(0);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>ERP Maintenance Console</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">


    <style>
        /* ============================================================
   BASE
============================================================ */

        body {
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
        }


        /* ============================================================
   MODAL
============================================================ */

        #maintenanceModal {
            backdrop-filter: blur(2px);
        }

        #maintenanceModalBox {
            animation: maintenanceModalIn 0.15s ease-out;
        }

        @keyframes maintenanceModalIn {

            from {
                opacity: 0;
                transform: translateY(-8px) scale(0.98);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }


        /* ============================================================
   MODULE CARD
============================================================ */

        .maintenance-card {
            transition:
                border-color 0.15s ease,
                box-shadow 0.15s ease;
        }

        .maintenance-card:hover {
            border-color: #cbd5e1;
        }


        /* ============================================================
   BUTTON
============================================================ */

        .maintenance-btn {
            min-width: 86px;
        }


        /* ============================================================
   SCROLLBAR
============================================================ */

        ::-webkit-scrollbar {
            width: 7px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>

</head>


<body class="bg-gray-100 min-h-screen p-4 md:p-6">


    <div class="max-w-5xl mx-auto">


        <!-- =========================================================
     WARNING HEADER
========================================================= -->

        <div class="bg-red-600 text-white p-4 mb-6 shadow">

            <div class="flex items-start gap-3">

                <div class="text-xl pt-0.5">

                    <i class="fas fa-triangle-exclamation"></i>

                </div>

                <div class="flex-1">

                    <h1 class="text-xl font-bold">

                        ERP Maintenance Console

                    </h1>

                    <p class="text-sm mt-1 opacity-90">

                        Handle with care. CLEAN permanently removes
                        the selected module's data.

                    </p>

                </div>

            </div>


            <div class="mt-4 bg-white/15 p-3 text-sm">

                <div class="font-semibold">

                    <i class="fas fa-shield-halved mr-1"></i>

                    Always protected:

                </div>


                <ul class="list-disc ml-5 mt-2 space-y-1">

                    <?php foreach (
                        PROTECTED_SUMMARY as $p
                    ): ?>

                        <li>
                            <?= htmlspecialchars($p) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        </div>


        <!-- =========================================================
     RESULT MESSAGE
========================================================= -->

        <?php if ($result): ?>

            <div
                class="p-4 mb-6 shadow text-sm font-medium border
    <?=
            $resultType === 'error'
                ? 'bg-red-50 text-red-800 border-red-300'
                : (
                    $resultType === 'clean'
                    ? 'bg-yellow-50 text-yellow-900 border-yellow-300'
                    : 'bg-green-50 text-green-800 border-green-300'
                )
    ?>">

                <div class="flex items-start gap-3">

                    <div>

                        <?php if ($resultType === 'error'): ?>

                            <i class="fas fa-circle-xmark"></i>

                        <?php elseif ($resultType === 'clean'): ?>

                            <i class="fas fa-broom"></i>

                        <?php else: ?>

                            <i class="fas fa-circle-check"></i>

                        <?php endif; ?>

                    </div>

                    <div>

                        <b>
                            <?= strtoupper(
                                htmlspecialchars($resultType)
                            ) ?>:
                        </b>

                        <?= htmlspecialchars(
                            (string)$result
                        ) ?>

                    </div>

                </div>

            </div>

        <?php endif; ?>


        <!-- =========================================================
     MAIN FORM
========================================================= -->

        <form
            method="post"
            id="maintenanceForm"
            class="space-y-4">


            <!-- Hidden fields controlled by modal -->

            <input
                type="hidden"
                name="action"
                id="maintenanceAction"
                value="">

            <input
                type="hidden"
                name="module"
                id="maintenanceModule"
                value="">


            <?php foreach (
                $MODULES as $key => $mod
            ): ?>


                <!-- =========================================================
     MODULE CARD
========================================================= -->

                <div
                    class="maintenance-card bg-white border border-gray-200 shadow-sm p-5">

                    <div
                        class="flex justify-between items-start flex-wrap gap-4">


                        <!-- LEFT -->
                        <div
                            class="flex-1 min-w-[260px]">

                            <div class="flex items-center gap-2">

                                <div
                                    class="w-8 h-8 flex items-center justify-center bg-blue-50 text-blue-600">

                                    <i class="fas fa-database"></i>

                                </div>

                                <h2
                                    class="font-bold text-gray-800">
                                    <?= htmlspecialchars(
                                        $mod['label']
                                    ) ?>
                                </h2>

                            </div>


                            <p
                                class="text-sm text-gray-600 mt-3">
                                <?= htmlspecialchars(
                                    $mod['detail']
                                ) ?>
                            </p>


                            <div
                                class="mt-3 text-xs space-y-1.5">

                                <div>

                                    <span
                                        class="inline-block w-16 font-semibold text-yellow-700">
                                        CLEANS:
                                    </span>

                                    <span class="text-gray-500">
                                        <?= htmlspecialchars(
                                            $mod['detail']
                                        ) ?>
                                    </span>

                                </div>


                                <div>

                                    <span
                                        class="inline-block w-16 font-semibold text-green-700">
                                        KEEPS:
                                    </span>

                                    <span class="text-gray-500">

                                        <?= htmlspecialchars(
                                            $mod['protect']
                                                ?? 'structure only'
                                        ) ?>

                                    </span>

                                </div>


                                <?php if (
                                    !empty($mod['depends'])
                                ): ?>

                                    <div>

                                        <span
                                            class="inline-block w-16 font-semibold text-indigo-700">
                                            NEEDS:
                                        </span>

                                        <span class="text-gray-500">

                                            <?= htmlspecialchars(
                                                $mod['depends']
                                            ) ?>

                                            (seed those first)

                                        </span>

                                    </div>

                                <?php endif; ?>

                            </div>

                        </div>


                        <!-- RIGHT BUTTONS -->

                        <div
                            class="flex gap-2 items-start">


                            <?php if (
                                !empty($mod['clean'])
                            ): ?>

                                <button
                                    type="button"
                                    class="maintenance-btn bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 text-sm font-semibold"
                                    onclick="openMaintenanceModal(
                    'clean',
                    '<?= htmlspecialchars(
                                    $key,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>',
                    '<?= htmlspecialchars(
                                    $mod['label'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>'
                )">

                                    <i
                                        class="fas fa-broom mr-1"></i>

                                    Clean

                                </button>

                            <?php endif; ?>


                            <?php if (
                                !empty($mod['seed'])
                            ): ?>

                                <button
                                    type="button"
                                    class="maintenance-btn bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 text-sm font-semibold"
                                    onclick="openMaintenanceModal(
                    'seed',
                    '<?= htmlspecialchars(
                                    $key,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>',
                    '<?= htmlspecialchars(
                                    $mod['label'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>'
                )">

                                    <i
                                        class="fas fa-seedling mr-1"></i>

                                    Seed

                                </button>

                            <?php endif; ?>


                        </div>

                    </div>

                </div>


            <?php endforeach; ?>


        </form>


        <!-- =========================================================
     FOOTER NOTE
========================================================= -->

        <div
            class="text-center text-xs text-gray-400 mt-8 leading-5">

            Recommended order after a full wipe:

            <span class="text-gray-500">

                Seed Companies → Departments → Cost Centers →
                Projects → Vendors → Tax → Users → Procurement →
                Finance Demo.

            </span>

            <br>

            Your login
            <b class="text-gray-500">users.id=1</b>
            is untouchable — even if everything else is emptied.

        </div>


    </div>


    <!-- =========================================================
     CUSTOM CONFIRMATION MODAL
========================================================= -->

    <div
        id="maintenanceModal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4">

        <div
            id="maintenanceModalBox"
            class="w-full max-w-md bg-white shadow-2xl border border-gray-300">


            <!-- =================================================
             MODAL HEADER
        ================================================== -->

            <div
                id="maintenanceModalHeader"
                class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">

                <div class="flex items-center gap-3">


                    <div
                        id="maintenanceModalIcon"
                        class="w-9 h-9 flex items-center justify-center bg-yellow-100 text-yellow-700">

                        <i
                            id="maintenanceModalIconElement"
                            class="fas fa-broom"></i>

                    </div>


                    <div>

                        <h3
                            id="maintenanceModalTitle"
                            class="text-base font-bold text-gray-800">
                            Confirm Action
                        </h3>

                        <p
                            id="maintenanceModalSubtitle"
                            class="text-xs text-gray-500">
                            Please confirm this action
                        </p>

                    </div>

                </div>


                <button
                    type="button"
                    onclick="closeMaintenanceModal()"
                    class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-700 hover:bg-gray-100">

                    <i class="fas fa-times"></i>

                </button>

            </div>


            <!-- =================================================
             MODAL BODY
        ================================================== -->

            <div class="px-5 py-5">


                <!-- Warning -->

                <div
                    id="maintenanceWarning"
                    class="border border-yellow-300 bg-yellow-50 p-3 mb-4 text-sm">

                    <div class="flex gap-3">

                        <i
                            id="maintenanceWarningIcon"
                            class="fas fa-triangle-exclamation mt-0.5 text-yellow-600"></i>


                        <div class="flex-1">

                            <p
                                id="maintenanceMessage"
                                class="font-medium text-gray-800">
                                Are you sure?
                            </p>


                            <p
                                id="maintenanceDetail"
                                class="text-xs text-gray-500 mt-1">
                                Please confirm this action.
                            </p>

                        </div>

                    </div>

                </div>


                <!-- Protected Information -->

                <div
                    id="maintenanceProtected"
                    class="bg-gray-50 border border-gray-200 p-3 text-xs text-gray-600">

                    <div
                        class="font-semibold text-gray-700 mb-1">

                        <i
                            class="fas fa-shield-halved mr-1 text-blue-600"></i>

                        Protected Records

                    </div>


                    <div>

                        Protected records such as Super Admin,
                        permissions and default company will remain safe.

                    </div>

                </div>

            </div>


            <!-- =================================================
             MODAL FOOTER
        ================================================== -->

            <div
                class="px-5 py-3 bg-gray-50 border-t border-gray-200 flex justify-end gap-2">


                <button
                    type="button"
                    onclick="closeMaintenanceModal()"
                    class="px-4 py-2 border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-100">

                    Cancel

                </button>


                <button
                    type="button"
                    id="maintenanceConfirmButton"
                    onclick="submitMaintenanceAction()"
                    class="px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white text-sm font-semibold">

                    <i
                        id="maintenanceConfirmIcon"
                        class="fas fa-broom mr-1"></i>

                    <span
                        id="maintenanceConfirmText">
                        Continue
                    </span>

                </button>

            </div>


        </div>

    </div>


    <!-- =========================================================
     JAVASCRIPT
========================================================= -->

    <script>
        let maintenanceAction = '';

        let maintenanceModule = '';


        /* ============================================================
           OPEN MODAL
        ============================================================ */

        function openMaintenanceModal(
            action,
            module,
            label
        ) {

            maintenanceAction = action;

            maintenanceModule = module;


            const modal =
                document.getElementById(
                    'maintenanceModal'
                );


            const title =
                document.getElementById(
                    'maintenanceModalTitle'
                );


            const subtitle =
                document.getElementById(
                    'maintenanceModalSubtitle'
                );


            const message =
                document.getElementById(
                    'maintenanceMessage'
                );


            const detail =
                document.getElementById(
                    'maintenanceDetail'
                );


            const iconBox =
                document.getElementById(
                    'maintenanceModalIcon'
                );


            const icon =
                document.getElementById(
                    'maintenanceModalIconElement'
                );


            const warning =
                document.getElementById(
                    'maintenanceWarning'
                );


            const warningIcon =
                document.getElementById(
                    'maintenanceWarningIcon'
                );


            const confirmButton =
                document.getElementById(
                    'maintenanceConfirmButton'
                );


            const confirmIcon =
                document.getElementById(
                    'maintenanceConfirmIcon'
                );


            const confirmText =
                document.getElementById(
                    'maintenanceConfirmText'
                );


            /* ========================================================
               CLEAN
            ======================================================== */

            if (action === 'clean') {

                title.textContent =
                    'Clean Module Data';


                subtitle.textContent =
                    'Permanent data removal';


                message.textContent =
                    'Are you sure you want to clean "' +
                    label +
                    '"?';


                detail.textContent =
                    'This action will permanently remove the selected module data.';


                iconBox.className =
                    'w-9 h-9 flex items-center justify-center bg-yellow-100 text-yellow-700';


                icon.className =
                    'fas fa-broom';


                warning.className =
                    'border border-yellow-300 bg-yellow-50 p-3 mb-4 text-sm';


                warningIcon.className =
                    'fas fa-triangle-exclamation mt-0.5 text-yellow-600';


                confirmButton.className =
                    'px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white text-sm font-semibold';


                confirmIcon.className =
                    'fas fa-broom mr-1';


                confirmText.textContent =
                    'Clean Data';

            }


            /* ========================================================
               SEED
            ======================================================== */
            else {

                title.textContent =
                    'Seed Demo Data';


                subtitle.textContent =
                    'Create sample records';


                message.textContent =
                    'Do you want to seed demo data for "' +
                    label +
                    '"?';


                detail.textContent =
                    'Sample records will be created for this module.';


                iconBox.className =
                    'w-9 h-9 flex items-center justify-center bg-emerald-100 text-emerald-700';


                icon.className =
                    'fas fa-seedling';


                warning.className =
                    'border border-emerald-300 bg-emerald-50 p-3 mb-4 text-sm';


                warningIcon.className =
                    'fas fa-seedling mt-0.5 text-emerald-600';


                confirmButton.className =
                    'px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold';


                confirmIcon.className =
                    'fas fa-seedling mr-1';


                confirmText.textContent =
                    'Seed Data';

            }


            /* ========================================================
               SHOW MODAL
            ======================================================== */

            modal.classList.remove(
                'hidden'
            );

            modal.classList.add(
                'flex'
            );


            document.body.classList.add(
                'overflow-hidden'
            );

        }


        /* ============================================================
           CLOSE MODAL
        ============================================================ */

        function closeMaintenanceModal() {

            const modal =
                document.getElementById(
                    'maintenanceModal'
                );


            modal.classList.add(
                'hidden'
            );


            modal.classList.remove(
                'flex'
            );


            document.body.classList.remove(
                'overflow-hidden'
            );


            maintenanceAction = '';

            maintenanceModule = '';

        }


        /* ============================================================
           SUBMIT ACTION
        ============================================================ */

        function submitMaintenanceAction() {

            if (
                !maintenanceAction ||
                !maintenanceModule
            ) {
                return;
            }


            const actionInput =
                document.getElementById(
                    'maintenanceAction'
                );


            const moduleInput =
                document.getElementById(
                    'maintenanceModule'
                );


            actionInput.value =
                maintenanceAction;


            moduleInput.value =
                maintenanceModule;


            const form =
                document.getElementById(
                    'maintenanceForm'
                );


            if (!form) {
                return;
            }


            /* Disable button to prevent double click */

            const button =
                document.getElementById(
                    'maintenanceConfirmButton'
                );


            if (button) {

                button.disabled = true;

                button.classList.add(
                    'opacity-60',
                    'cursor-not-allowed'
                );

            }


            form.submit();

        }


        /* ============================================================
           CLICK OUTSIDE MODAL
        ============================================================ */

        document
            .getElementById('maintenanceModal')
            .addEventListener(
                'click',
                function(event) {

                    if (
                        event.target === this
                    ) {

                        closeMaintenanceModal();

                    }

                }
            );


        /* ============================================================
           ESC KEY
        ============================================================ */

        document.addEventListener(
            'keydown',
            function(event) {

                if (
                    event.key === 'Escape'
                ) {

                    const modal =
                        document.getElementById(
                            'maintenanceModal'
                        );


                    if (
                        modal &&
                        !modal.classList.contains(
                            'hidden'
                        )
                    ) {

                        closeMaintenanceModal();

                    }

                }

            }
        );
    </script>


</body>

</html>