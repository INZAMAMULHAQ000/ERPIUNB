<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$pageTitle = 'Payroll Finance Reports';
require_once '../../../includes/header.php';
require_once '../../../helpers/redirect.php';

require_once __DIR__ . '/../../../../helpers/rbac.php';
if (!hasPermission('payrollfinance.view')) { die('Access Denied'); }

$db = getDB();

?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="pb-6 border-b border-gray-200 mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Payroll Finance Reports</h1>
        <p class="mt-2 text-sm text-gray-600">Access all payroll-related financial reports</p>
    </div>
    <?php if (hasPermission('payrollfinance.report')): ?>
    <div class="space-y-4">
        <!-- Payroll Cost Report -->
        <div class="flex items-center p-4 bg-white rounded-lg shadow hover:shadow-lg transition-shadow">
            <div class="flex-shrink-0">
                <i class="fas fa-dollar-sign text-xl bg-blue-100 text-blue-600 rounded-full p-3"></i>
            </div>
            <div class="ml-4 w-0 flex-1">
                <h3 class="text-lg font-medium text-gray-900">Payroll Cost Report</h3>
                <p class="mt-1 text-sm text-gray-600">Detailed breakdown of total payroll costs</p>
            </div>
            <div class="ml-4 flex-shrink-0">
                <a href="payroll_cost.php" class="inline-flex items-center px-3 py-1 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700">
                    View Report <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>
        <!-- Salary Expense Report -->
        <div class="flex items-center p-4 bg-white rounded-lg shadow hover:shadow-lg transition-shadow">
            <div class="flex-shrink-0">
                <i class="fas fa-money-bill-wave text-xl bg-green-100 text-green-600 rounded-full p-3"></i>
            </div>
            <div class="ml-4 w-0 flex-1">
                <h3 class="text-lg font-medium text-gray-900">Salary Expense Report</h3>
                <p class="mt-1 text-sm text-gray-600">Analysis of salary expenses by department and employee type</p>
            </div>
            <div class="ml-4 flex-shrink-0">
                <a href="salary_expense.php" class="inline-flex items-center px-3 py-1 bg-green-600 text-white text-sm font-medium rounded hover:bg-green-700">
                    View Report <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>
        <!-- Department Payroll Report -->
        <div class="flex items-center p-4 bg-white rounded-lg shadow hover:shadow-lg transition-shadow">
            <div class="flex-shrink-0">
                <i class="fas fa-building text-xl bg-purple-100 text-purple-600 rounded-full p-3"></i>
            </div>
            <div class="ml-4 w-0 flex-1">
                <h3 class="text-lg font-medium text-gray-900">Department Payroll Report</h3>
                <p class="mt-1 text-sm text-gray-600">Payroll expenses broken down by department</p>
            </div>
            <div class="ml-4 flex-shrink-0">
                <a href="department_payroll.php" class="inline-flex items-center px-3 py-1 bg-purple-600 text-white text-sm font-medium rounded hover:bg-purple-700">
                    View Report <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>
        <!-- Employee Payable Report -->
        <div class="flex items-center p-4 bg-white rounded-lg shadow hover:shadow-lg transition-shadow">
            <div class="flex-shrink-0">
                <i class="fas fa-user-tie text-xl bg-indigo-100 text-indigo-600 rounded-full p-3"></i>
            </div>
            <div class="ml-4 w-0 flex-1">
                <h3 class="text-lg font-medium text-gray-900">Employee Payable Report</h3>
                <p class="mt-1 text-sm text-gray-600">Outstanding payments to employees</p>
            </div>
            <div class="ml-4 flex-shrink-0">
                <a href="employee_payable.php" class="inline-flex items-center px-3 py-1 bg-indigo-600 text-white text-sm font-medium rounded hover:bg-indigo-700">
                    View Report <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>
        <!-- Payroll Deduction Report -->
        <div class="flex items-center p-4 bg-white rounded-lg shadow hover:shadow-lg transition-shadow">
            <div class="flex-shrink-0">
                <i class="fas fa-subtract text-xl bg-red-100 text-red-600 rounded-full p-3"></i>
            </div>
            <div class="ml-4 w-0 flex-1">
                <h3 class="text-lg font-medium text-gray-900">Payroll Deduction Report</h3>
                <p class="mt-1 text-sm text-gray-600">Analysis of all deductions including taxes and insurance</p>
            </div>
            <div class="ml-4 flex-shrink-0">
                <a href="deduction_report.php" class="inline-flex items-center px-3 py-1 bg-red-600 text-white text-sm font-medium rounded hover:bg-red-700">
                    View Report <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>
        <!-- Employer Contribution Report -->
        <div class="flex items-center p-4 bg-white rounded-lg shadow hover:shadow-lg transition-shadow">
            <div class="flex-shrink-0">
                <i class="fas fa-plus-circle text-xl bg-teal-100 text-teal-600 rounded-full p-3"></i>
            </div>
            <div class="ml-4 w-0 flex-1">
                <h3 class="text-lg font-medium text-gray-900">Employer Contribution Report</h3>
                <p class="mt-1 text-sm text-gray-600">Employer contributions to taxes, benefits, and retirement</p>
            </div>
            <div class="ml-4 flex-shrink-0">
                <a href="employer_contribution.php" class="inline-flex items-center px-3 py-1 bg-teal-600 text-white text-sm font-medium rounded hover:bg-teal-700">
                    View Report <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>
        <!-- Payroll Payment Report -->
        <div class="flex items-center p-4 bg-white rounded-lg shadow hover:shadow-lg transition-shadow">
            <div class="flex-shrink-0">
                <i class="fas fa-credit-card text-xl bg-orange-100 text-orange-600 rounded-full p-3"></i>
            </div>
            <div class="ml-4 w-0 flex-1">
                <h3 class="text-lg font-medium text-gray-900">Payroll Payment Report</h3>
                <p class="mt-1 text-sm text-gray-600">Record of all payroll payments made</p>
            </div>
            <div class="ml-4 flex-shrink-0">
                <a href="payment_report.php" class="inline-flex items-center px-3 py-1 bg-orange-600 text-white text-sm font-medium rounded hover:bg-orange-700">
                    View Report <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>
        <!-- Payroll Reconciliation Report -->
        <div class="flex items-center p-4 bg-white rounded-lg shadow hover:shadow-lg transition-shadow">
            <div class="flex-shrink-0">
                <i class="fas fa-balance-scale text-xl bg-yellow-100 text-yellow-600 rounded-full p-3"></i>
            </div>
            <div class="ml-4 w-0 flex-1">
                <h3 class="text-lg font-medium text-gray-900">Payroll Reconciliation Report</h3>
                <p class="mt-1 text-sm text-gray-600">Reconciliation of payroll expenses with general ledger</p>
            </div>
            <div class="ml-4 flex-shrink-0">
                <a href="reconciliation_report.php" class="inline-flex items-center px-3 py-1 bg-yellow-600 text-white text-sm font-medium rounded hover:bg-yellow-700">
                    View Report <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>
        <!-- Monthly Payroll Summary -->
        <div class="flex items-center p-4 bg-white rounded-lg shadow hover:shadow-lg transition-shadow">
            <div class="flex-shrink-0">
                <i class="fas fa-calendar-alt text-xl bg-pink-100 text-pink-600 rounded-full p-3"></i>
            </div>
            <div class="ml-4 w-0 flex-1">
                <h3 class="text-lg font-medium text-gray-900">Monthly Payroll Summary</h3>
                <p class="mt-1 text-sm text-gray-600">Summary of payroll expenses for each month</p>
            </div>
            <div class="ml-4 flex-shrink-0">
                <a href="monthly_summary.php" class="inline-flex items-center px-3 py-1 bg-pink-600 text-white text-sm font-medium rounded hover:bg-pink-700">
                    View Report <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>
        <!-- Yearly Payroll Summary -->
        <div class="flex items-center p-4 bg-white rounded-lg shadow hover:shadow-lg transition-shadow">
            <div class="flex-shrink-0">
                <i class="fas fa-chart-line text-xl bg-gray-100 text-gray-600 rounded-full p-3"></i>
            </div>
            <div class="ml-4 w-0 flex-1">
                <h3 class="text-lg font-medium text-gray-900">Yearly Payroll Summary</h3>
                <p class="mt-1 text-sm text-gray-600">Annual summary of payroll expenses and trends</p>
            </div>
            <div class="ml-4 flex-shrink-0">
                <a href="yearly_summary.php" class="inline-flex items-center px-3 py-1 bg-gray-600 text-white text-sm font-medium rounded hover:bg-gray-700">
                    View Report <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
        <p class="text-sm text-yellow-800 flex items-center">
            <i class="fas fa-info-circle mr-2"></i>
            You do not have permission to view payroll finance reports.
        </p>
    </div>
    <?php endif; ?>
</div>
<?php require_once '../../../includes/footer.php'; ?>
