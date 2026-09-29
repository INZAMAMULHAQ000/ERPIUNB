<?php
if (hasPermission('payroll_finance.view')) {
    $db = getDB();
    $sql = "SELECT SUM(pr.total_net) as pending_amount 
            FROM payroll_runs pr
            LEFT JOIN payroll_finance_postings pfp ON pr.id = pfp.payroll_run_id AND pfp.status = 'Posted'
            WHERE pr.status = 'approved' AND pfp.id IS NULL";
    $row = $db->query($sql)->fetch();
    $pendingAmount = $row['pending_amount'] ?? 0;
    $formattedAmount = formatPayrollAmount($pendingAmount);
    ?>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4 border border-gray-200 dark:border-gray-700">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <i class="fas fa-money-bill-wave text-green-500 text-2xl"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending Payroll</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100"><?= $formattedAmount ?></p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    As of <?= (new DateTime())->format('M j, Y g:ia') ?>
                </p>
            </div>
        </div>
    </div>
    <?php
}
