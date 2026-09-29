<?php
/**
 * Payroll Finance Integration Dashboard
 * Phase F-10 - Payroll & Finance Integration
 *
 * @package XXX
 * @submodule Finance.F10
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$pageTitle = 'Payroll Finance Integration';
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../helpers/rbac.php';
require_once __DIR__ . '/../../../helpers/finance_helpers.php';
require_once __DIR__ . '/../../../helpers/redirect.php';

if (!hasPermission('payrollfinance.view')) { redirect_to('no-access.php'); }

$db = getDB();

// Get pending payroll count
$pendingStmt = $db->prepare("
    SELECT COUNT(*) as count, COALESCE(SUM(payroll_total), 0) as total_net
    FROM payroll_reconciliation prr
    LEFT JOIN payroll_finance_postings pfp ON prr.payroll_run_id = pfp.payroll_run_id AND pfp.status = 'Posted'
    WHERE pfp.id IS NULL");
$pendingStmt->execute();
$pending = $pendingStmt->fetch();

// Get posted payroll count
$postedStmt = $db->prepare("
    SELECT COUNT(*) as count, COALESCE(SUM(total_net), 0) as total_net
    FROM payroll_finance_postings
    WHERE status = 'Posted'");
$postedStmt->execute();
$posted = $postedStmt->fetch();

// Get total payroll cost this period
$costStmt = $db->prepare("
    SELECT COALESCE(SUM(total_net), 0) as total_net
    FROM payroll_finance_postings
    WHERE status = 'Posted'");
$costStmt->execute();
$cost = $costStmt->fetch();

// Get salary payable
$payableStmt = $db->prepare("
    SELECT COALESCE(SUM(total_amount), 0) as amount
    FROM payroll_payment_batches
    WHERE status NOT IN ('Paid', 'Failed')");
$payableStmt->execute();
$payable = $payableStmt->fetch();

// Get reconciliation summary
$reconStmt = $db->prepare("
    SELECT status, COUNT(*) as run_count, COALESCE(SUM(payroll_total), 0) as total_calculated
    FROM payroll_reconciliation
    GROUP BY status");
$reconStmt->execute();
$recon = $reconStmt->fetchAll();
?>

<div class="max-w-7xl mx-auto px-4 py-8">
    
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Payroll Finance Integration</h1>
        <p class="text-gray-600">Phase F10 - Bridge between Payroll and Finance</p>
    </div>
    
    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <!-- Pending Payments Card -->
        <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-blue-500 hover:shadow-md transition-shadow">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-clock text-blue-500 text-3xl"></i>
                </div>
                <div class="ml-4 flex-1">
                    <p class="text-sm text-gray-500">Pending Payroll</p>
                    <p class="text-2xl font-bold text-gray-900"><?= formatMoney($pending['total_net'] ?? 0) ?></p>
                    <p class="text-sm text-gray-400">Awaiting finance posting</p>
                </div>
            </div>
        </div>
        
        <!-- Posted Payments Card -->
        <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-green-500 hover:shadow-md transition-shadow">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-check text-green-500 text-3xl"></i>
                </div>
                <div class="ml-4 flex-1">
                    <p class="text-sm text-gray-500">Posted to Finance</p>
                    <p class="text-2xl font-bold text-gray-900"><?= formatMoney($posted['total_net'] ?? 0) ?></p>
                    <p class="text-sm text-gray-400">Successfully posted to GL</p>
                </div>
            </div>
        </div>
        
        <!-- Payroll Cost Card -->
        <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-purple-500 hover:shadow-md transition-shadow">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-dollar-sign text-purple-500 text-3xl"></i>
                </div>
                <div class="ml-4 flex-1">
                    <p class="text-sm text-gray-500">Current Payroll Cost</p>
                    <p class="text-2xl font-bold text-gray-900"><?= formatMoney($cost['total_net'] ?? 0) ?></p>
                    <p class="text-sm text-gray-400">Posted net salary</p>
                </div>
            </div>
        </div>
        
        <!-- Salary Payable Card -->
        <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-red-500 hover:shadow-md transition-shadow">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-user-tag text-red-500 text-3xl"></i>
                </div>
                <div class="ml-4 flex-1">
                    <p class="text-sm text-gray-500">Salary Payable</p>
                    <p class="text-2xl font-bold text-gray-900"><?= formatMoney($payable['amount'] ?? 0) ?></p>
                    <p class="text-sm text-gray-400">Outstanding employee payments</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <a href="mappings/" class="bg-white rounded-lg shadow-sm p-4 border border-gray-200 hover:border-blue-500 hover:shadow-md flex items-center">
            <i class="fas fa-layer-group text-blue-500 text-xl mr-3"></i>
            <div>
                <p class="text-sm text-gray-600">GL Mapping</p>
                <p class="text-xs text-gray-400">Configure component-to-account mappings</p>
            </div>
        </a>
        <a href="postings/" class="bg-white rounded-lg shadow-sm p-4 border border-gray-200 hover:border-green-500 hover:shadow-md flex items-center">
            <i class="fas fa-receipt text-green-500 text-xl mr-3"></i>
            <div>
                <p class="text-sm text-gray-600">Payroll Posting</p>
                <p class="text-xs text-gray-400">Post approved payroll to GL</p>
            </div>
        </a>
        <a href="payables/" class="bg-white rounded-lg shadow-sm p-4 border border-gray-200 hover:border-red-500 hover:shadow-md flex items-center">
            <i class="fas fa-hand-holding-usd text-red-500 text-xl mr-3"></i>
            <div>
                <p class="text-sm text-gray-600">Payroll Payables</p>
                <p class="text-xs text-gray-400">Manage employee payables</p>
            </div>
        </a>
        <a href="payments/" class="bg-white rounded-lg shadow-sm p-4 border border-gray-200 hover:border-orange-500 hover:shadow-md flex items-center">
            <i class="fas fa-money-bill-wave text-orange-500 text-xl mr-3"></i>
            <div>
                <p class="text-sm text-gray-600">Salary Payments</p>
                <p class="text-xs text-gray-400">Create payment batches</p>
            </div>
        </a>
    </div>
    
    <!-- Navigation Shortcuts -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <a href="deductions/" class="bg-white rounded-lg shadow-sm p-4 border border-gray-200 hover:border-yellow-500 hover:shadow-md flex items-center">
            <i class="fas fa-minus text-yellow-500 text-xl mr-3"></i>
            <div>
                <p class="text-sm text-gray-600">Deductions</p>
                <p class="text-xs text-gray-400">Manage payroll deductions</p>
            </div>
        </a>
        <a href="reconciliation/" class="bg-white rounded-lg shadow-sm p-4 border border-gray-200 hover:border-teal-500 hover:shadow-md flex items-center">
            <i class="fas fa-balance-scale text-teal-500 text-xl mr-3"></i>
            <div>
                <p class="text-sm text-gray-600">Reconciliation</p>
                <p class="text-xs text-gray-400">Compare payroll vs finance vs bank</p>
            </div>
        </a>
            <a href="reports/" class="bg-white rounded-lg shadow-sm p-4 border border-gray-200 hover:border-gray-400 hover:shadow-md flex items-center">
                <i class="fas fa-chart-bar text-gray-500 text-xl mr-3"></i>
                <div>
                    <p class="text-sm text-gray-600">Reports</p>
                    <p class="text-xs text-gray-400">Payroll finance reports</p>
                </div>
            </a>
        </div>
    
</div>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>