<?php

// Payroll Deduction Functions
function getPayrollDeductionTotals($db, $search = '', $deductionType = '') {
    $where = [];
    $params = [];
    
    if (!empty($search)) {
        $where[] = "pd.description LIKE ?";
        $params[] = "%$search%";
    }
    
    if (!empty($deductionType)) {
        $where[] = "pdt.deduction_type = ?";
        $params[] = $deductionType;
    }
    
    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "
        SELECT 
            pr.id as payroll_run_id,
            pr.payroll_run_name,
            pdt.deduction_type,
            pdt.description,
            SUM(pd.amount) as amount,
            COUNT(DISTINCT pd.employee_id) as employee_count
        FROM payroll_deductions pd
        JOIN payroll_runs pr ON pd.payroll_run_id = pr.id
        JOIN payroll_deduction_types pdt ON pd.deduction_type_id = pdt.id
        $whereClause
        GROUP BY pr.id, pdt.deduction_type, pdt.description
        ORDER BY pr.payroll_run_date DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getPayrollDeductionTypes($db) {
    $sql = "
        SELECT deduction_type, description
        FROM payroll_deduction_types
        WHERE status = 'Active'
        ORDER BY deduction_type
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Helper function to get payroll runs for dropdowns
function getPayrollRuns($db, $status = null) {
    $where = [];
    $params = [];
    
    if ($status !== null) {
        $where[] = "status = ?";
        $params[] = $status;
    }
    
    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "
        SELECT pr.id, pr.payroll_run_name, pr.payroll_run_date, pr.status
        FROM payroll_runs pr
        $whereClause
        ORDER BY pr.payroll_run_date DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll();
}

?>
