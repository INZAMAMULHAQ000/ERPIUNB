<?php
/**
 * Payroll GL Mapping Process
 * Handle add/edit/delete operations for GL Mappings
 * Phase F10 - Payroll Finance Integration
 */

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../helpers/security.php';
require_once __DIR__ . '/../../../../helpers/rbac.php';
require_once __DIR__ . '/../../../../helpers/redirect.php';
require_once __DIR__ . '/../../../../middleware/auth.php';
require_once __DIR__ . '/../../helpers/finance_helpers.php';

requireAuth();

if (!hasPermission('payroll_finance.edit')) {
    redirect_to('no-access.php');
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'create';
$db = getDB();
$allowedTypes = ['expense', 'liability', 'asset'];
$allowedStatus = ['Active', 'Inactive'];

switch ($action) {
    case 'create':
        $payrollComponent = trim($_POST['payroll_component'] ?? '');
        $chartAccountId = (int)($_POST['chart_account_id'] ?? 0);
        $mappingType = $_POST['mapping_type'] ?? 'expense';
        $status = $_POST['status'] ?? 'Active';

        if ($payrollComponent === '' || $chartAccountId <= 0) {
            flash('error', 'Payroll component and GL account are required.');
            redirect_to('add.php');
        }
        if (!in_array($mappingType, $allowedTypes, true)) {
            $mappingType = 'expense';
        }
        if (!in_array($status, $allowedStatus, true)) {
            $status = 'Active';
        }

        $db->prepare("
            INSERT INTO payroll_finance_mappings (payroll_component, chart_account_id, mapping_type, status)
            VALUES (?, ?, ?, ?)
        ")->execute([$payrollComponent, $chartAccountId, $mappingType, $status]);
        break;

    case 'edit':
        $id = (int)($_POST['id'] ?? 0);
        $payrollComponent = trim($_POST['payroll_component'] ?? '');
        $chartAccountId = (int)($_POST['chart_account_id'] ?? 0);
        $mappingType = $_POST['mapping_type'] ?? 'expense';
        $status = $_POST['status'] ?? 'Active';

        if ($id <= 0 || $payrollComponent === '' || $chartAccountId <= 0) {
            flash('error', 'Invalid mapping data.');
            redirect_to('index.php');
        }
        if (!in_array($mappingType, $allowedTypes, true)) {
            $mappingType = 'expense';
        }
        if (!in_array($status, $allowedStatus, true)) {
            $status = 'Active';
        }

        $db->prepare("
            UPDATE payroll_finance_mappings
            SET payroll_component = ?, chart_account_id = ?, mapping_type = ?, status = ?
            WHERE id = ?
        ")->execute([$payrollComponent, $chartAccountId, $mappingType, $status, $id]);
        break;

    case 'delete':
        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0) {
            $db->prepare("DELETE FROM payroll_finance_mappings WHERE id = ?")->execute([$id]);
        }
        break;
}

redirect_to('index.php');