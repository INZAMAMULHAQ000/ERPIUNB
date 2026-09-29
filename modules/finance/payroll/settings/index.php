<?php
/**
 * Payroll Finance Settings
 * 
 * @package XXX
 * @submodule Finance.Payroll.Settings
 */

// Check permission
if (!hasPermission('payrollfinance.view')) {
    redirect_to('no-access.php');
}

// Include header
include_once __DIR__ . '/../../../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex justify-between items-center flex-wrap">
        <h1 class="text-2xl font-bold">Payroll Finance Settings</h1>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4">Payroll Finance Configuration</h2>
        <p class="text-gray-600 mb-4">Configure general settings for payroll finance integration.</p>
        
        <!-- Settings form would go here -->
        <div class="bg-blue-50 border-l-4 border-blue-600 p-4 text-blue-800">
            Settings management interface - to be implemented based on specific requirements.
            This may include:
            <ul class="ml-5">
                <li>Payroll integration toggle</li>
                <li>Default mapping settings</li>
                <li>Posting automation rules</li>
                <li>Notification preferences</li>
                <li>Reconciliation tolerance settings</li>
            </ul>
        </div>
    </div>
</div>

<?php
// Include footer
include_once __DIR__ . '/../../../includes/footer.php';
?>
