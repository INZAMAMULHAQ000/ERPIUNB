<?php
/**
 * Payroll Finance Reports
 * Phase F10 - Payroll & Finance Integration
 * 
 * @package XXX
 * @submodule Finance.F10.Reports
 */
$pageTitle = 'Payroll Finance Reports';
require_once __DIR__ . '/../../../../includes/header.php';
require_once __DIR__ . '/../../helpers/finance_helpers.php';

if (!hasPermission('payroll_finance.report')) {
    redirect_to('no-access.php');
}
?>

<div class="max-w-7xl mx-auto px-4 py-8">

    <div class="pb-6 border-b border-gray-200 mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Payroll Finance Reports</h1>
        <p class="mt-2 text-sm text-gray-600">Access all payroll-related financial reports</p>
    </div>

    <?php if (hasPermission('payroll_finance.report')): ?>
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

        <!-- Payroll Reconciliation Report -->
        <div class="flex items-center p-4 bg-white rounded-lg shadow hover:shadow-lg transition-shadow">
            <div class="flex-shrink-0">
                <i class="fas fa-balance-scale text-xl bg-yellow-100 text-yellow-600 rounded-full p-3"></i>
            </div>
            <div class="ml-4 w-0 flex-1">
                <h3 class="text-lg font-medium text-gray-900">Payroll Reconciliation Report</h3>
                <p class="mt-1 text-sm text-gray-600">Compare payroll, finance, and bank totals</p>
            </div>
            <div class="ml-4 flex-shrink-0">
                <a href="../reconciliation/" class="inline-flex items-center px-3 py-1 bg-yellow-600 text-white text-sm font-medium rounded hover:bg-yellow-700">
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
                <p class="mt-1 text-sm text-gray-600">Month-by-month payroll expense breakdown</p>
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
                <i class="fas fa-chart-line text-xl bg-indigo-100 text-indigo-600 rounded-full p-3"></i>
            </div>
            <div class="ml-4 w-0 flex-1">
                <h3 class="text-lg font-medium text-gray-900">Yearly Payroll Summary</h3>
                <p class="mt-1 text-sm text-gray-600">Annual payroll cost overview and trends</p>
            </div>
            <div class="ml-4 flex-shrink-0">
                <a href="yearly_summary.php" class="inline-flex items-center px-3 py-1 bg-indigo-600 text-white text-sm font-medium rounded hover:bg-indigo-700">
                    View Report <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>

        <!-- Employee Payable Report -->
        <div class="flex items-center p-4 bg-white rounded-lg shadow hover:shadow-lg transition-shadow">
            <div class="flex-shrink-0">
                <i class="fas fa-user-clock text-xl bg-red-100 text-red-600 rounded-full p-3"></i>
            </div>
            <div class="ml-4 w-0 flex-1">
                <h3 class="text-lg font-medium text-gray-900">Employee Payable Report</h3>
                <p class="mt-1 text-sm text-gray-600">Track outstanding employee salary payables</p>
            </div>
            <div class="ml-4 flex-shrink-0">
                <a href="employee_payable.php" class="inline-flex items-center px-3 py-1 bg-red-600 text-white text-sm font-medium rounded hover:bg-red-700">
                    View Report <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>

        <!-- Deduction Report -->
        <div class="flex items-center p-4 bg-white rounded-lg shadow hover:shadow-lg transition-shadow">
            <div class="flex-shrink-0">
                <i class="fas fa-minus-circle text-xl bg-orange-100 text-orange-600 rounded-full p-3"></i>
            </div>
            <div class="ml-4 w-0 flex-1">
                <h3 class="text-lg font-medium text-gray-900">Deduction Report</h3>
                <p class="mt-1 text-sm text-gray-600">All payroll deductions and their impact</p>
            </div>
            <div class="ml-4 flex-shrink-0">
                <a href="deduction_report.php" class="inline-flex items-center px-3 py-1 bg-orange-600 text-white text-sm font-medium rounded hover:bg-orange-700">
                    View Report <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>

        <!-- Employer Contribution Report -->
        <div class="flex items-center p-4 bg-white rounded-lg shadow hover:shadow-lg transition-shadow">
            <div class="flex-shrink-0">
                <i class="fas fa-building text-xl bg-teal-100 text-teal-600 rounded-full p-3"></i>
            </div>
            <div class="ml-4 w-0 flex-1">
                <h3 class="text-lg font-medium text-gray-900">Employer Contribution Report</h3>
                <p class="mt-1 text-sm text-gray-600">Employer social security and pension costs</p>
            </div>
            <div class="ml-4 flex-shrink-0">
                <a href="employer_contribution.php" class="inline-flex items-center px-3 py-1 bg-teal-600 text-white text-sm font-medium rounded hover:bg-teal-700">
                    View Report <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>

        <!-- Payment Report -->
        <div class="flex items-center p-4 bg-white rounded-lg shadow hover:shadow-lg transition-shadow">
            <div class="flex-shrink-0">
                <i class="fas fa-university text-xl bg-cyan-100 text-cyan-600 rounded-full p-3"></i>
            </div>
            <div class="ml-4 w-0 flex-1">
                <h3 class="text-lg font-medium text-gray-900">Payment Report</h3>
                <p class="mt-1 text-sm text-gray-600">Salary payment batch history and status</p>
            </div>
            <div class="ml-4 flex-shrink-0">
                <a href="payment_report.php" class="inline-flex items-center px-3 py-1 bg-cyan-600 text-white text-sm font-medium rounded hover:bg-cyan-700">
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

<?php require_once __DIR__ . '/../../../../includes/footer.php'; ?>