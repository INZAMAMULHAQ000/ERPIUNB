<?php
/**
 * F11 — Report Export Engine
 * Export reports to PDF, Excel, CSV
 */

$pageTitle = 'Export Reports';
require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once '../FinancialReportService.php';

if (!hasPermission('report.export')) { die('Access Denied'); }

$db = getDB();

// Get parameters
$reportType = $_GET['report_type'] ?? 'trial_balance';
$format = $_GET['format'] ?? 'csv';
$fyId = (int)($_GET['financial_year_id'] ?? 0);

// Default to active FY
if (!$fyId) {
    $activeFY = getActiveFinancialYear($db);
    $fyId = $activeFY ? (int)$activeFY['id'] : 0;
}

$financialYears = $db->query("SELECT id, year_name FROM financial_years ORDER BY start_date DESC")->fetchAll();

// Get company info for export header
$companyStmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'company_name'");
$companyName = $companyStmt->fetchColumn() ?: 'My Company';

// Handle export request
if (isset($_GET['export']) && $_GET['export'] === '1') {
    $report = null;
    $filename = $reportType . '_' . date('YmdHis');
    
    // Generate report data based on type
    switch ($reportType) {
        case 'trial_balance':
            $report = FinancialReportService::generateTrialBalance(['financial_year_id' => $fyId]);
            break;
        case 'profit_loss':
            $report = FinancialReportService::generateProfitLoss(['financial_year_id' => $fyId]);
            break;
        case 'balance_sheet':
            $report = FinancialReportService::generateBalanceSheet(['financial_year_id' => $fyId]);
            break;
        case 'cash_flow':
            $report = FinancialReportService::generateCashFlow(['financial_year_id' => $fyId]);
            break;
        case 'ar_aging':
            $report = FinancialReportService::generateARAging();
            break;
        case 'ap_aging':
            $report = FinancialReportService::generateAPAging();
            break;
        case 'general_ledger':
            // GL report
            $stmt = $db->prepare("
                SELECT gl.*, coa.account_code, coa.account_name, je.description, je.reference_no
                FROM general_ledger gl
                JOIN chart_of_accounts coa ON coa.id = gl.account_id
                LEFT JOIN journal_entries je ON je.id = gl.journal_entry_id
                WHERE gl.financial_year_id = ?
                ORDER BY gl.posting_date, gl.id
            ");
            $stmt->execute([$fyId]);
            $report = ['success' => true, 'data' => $stmt->fetchAll(), 'type' => 'general_ledger'];
            break;
        case 'tax_summary':
            $report = FinancialReportService::generateTaxSummary();
            break;
        case 'asset_register':
            $report = FinancialReportService::generateAssetRegister();
            break;
        case 'budget_vs_actual':
            $report = FinancialReportService::generateBudgetVsActual(['financial_year_id' => $fyId]);
            break;
        case 'payroll_summary':
            $report = FinancialReportService::generatePayrollSummary();
            break;
        default:
            $report = ['success' => false, 'message' => 'Unknown report type'];
    }
    
    if ($report && $report['success']) {
        $extension = $format === 'excel' ? 'xls' : ($format === 'pdf' ? 'pdf' : 'csv');
        $fileName = $filename . '.' . $extension;
        FinancialReportService::logReportExport($reportType, ['financial_year_id' => $fyId], $_SESSION['user_id'] ?? null, $format, $fileName);

        while (ob_get_level()) {
            ob_end_clean();
        }

        // Set headers based on format
        switch ($format) {
            case 'csv':
                exportCSV($report, $reportType, $companyName, $fyId, $fileName);
                break;
            case 'excel':
                exportExcel($report, $reportType, $companyName, $fyId, $fileName);
                break;
            case 'pdf':
                exportPDF($report, $reportType, $companyName, $fyId, $fileName);
                break;
        }
    }
    exit;
}

function normalizeReportForExport($report, $reportType) {
    $headers = [];
    $rows = [];

    switch ($reportType) {
        case 'trial_balance':
            $headers = ['Account Code', 'Account Name', 'Opening Dr', 'Opening Cr', 'Period Dr', 'Period Cr', 'Closing Dr', 'Closing Cr'];
            foreach ($report['lines'] ?? [] as $line) {
                $rows[] = [$line['account_code'], $line['account_name'], $line['opening_debit'], $line['opening_credit'], $line['period_debit'], $line['period_credit'], $line['closing_debit'], $line['closing_credit']];
            }
            $rows[] = ['TOTALS', '', $report['totals']['opening_debit'] ?? 0, $report['totals']['opening_credit'] ?? 0, $report['totals']['period_debit'] ?? 0, $report['totals']['period_credit'] ?? 0, $report['totals']['closing_debit'] ?? 0, $report['totals']['closing_credit'] ?? 0];
            break;
        case 'profit_loss':
            $headers = ['Item', 'Amount'];
            foreach ($report['profit_loss'] ?? [] as $item) {
                $rows[] = [$item['label'], $item['amount']];
            }
            break;
        case 'balance_sheet':
            $headers = ['Section', 'Item', 'Amount'];
            foreach ($report['balance_sheet'] ?? [] as $section => $items) {
                if (isset($items['label'])) {
                    $rows[] = [$section, $items['label'], $items['amount']];
                    continue;
                }
                foreach ($items as $item) {
                    $rows[] = [ucwords(str_replace('_', ' ', $section)), $item['label'], $item['amount']];
                }
            }
            break;
        case 'cash_flow':
            $headers = ['Activity', 'Item', 'Amount'];
            foreach (['operating', 'investing', 'financing', 'non_cash'] as $section) {
                $block = $report['cash_flow'][$section] ?? null;
                if (!$block) continue;
                $rows[] = [$block['label'], 'Total', $block['amount']];
                foreach ($block['items'] ?? [] as $item) {
                    $rows[] = [$block['label'], trim(($item['account_code'] ? $item['account_code'] . ' - ' : '') . $item['account_name']), $item['amount']];
                }
            }
            $rows[] = ['Cash', 'Opening Cash', $report['cash_flow']['opening_cash']['amount'] ?? 0];
            $rows[] = ['Cash', 'Net Cash Flow', $report['cash_flow']['net_cash_flow']['amount'] ?? 0];
            $rows[] = ['Cash', 'Closing Cash', $report['cash_flow']['closing_cash']['amount'] ?? 0];
            break;
        case 'ar_aging':
            $headers = ['Customer', 'Current', '31-60 Days', '61-90 Days', '90+ Days', 'Total'];
            foreach ($report['client_aging'] ?? [] as $aging) {
                $rows[] = [$aging['name'], $aging['current'], $aging['31_60'], $aging['61_90'], $aging['90_plus'], $aging['total']];
            }
            break;
        case 'ap_aging':
            $headers = ['Vendor', 'Current', '31-60 Days', '61-90 Days', '90+ Days', 'Total'];
            foreach ($report['vendor_aging'] ?? [] as $aging) {
                $rows[] = [$aging['name'], $aging['current'], $aging['31_60'], $aging['61_90'], $aging['90_plus'], $aging['total']];
            }
            break;
        case 'general_ledger':
            $headers = ['Date', 'Account Code', 'Account Name', 'Voucher', 'Reference', 'Description', 'Debit', 'Credit', 'Running Balance'];
            foreach (($report['rows'] ?? $report['data'] ?? []) as $row) {
                $rows[] = [$row['posting_date'], $row['account_code'], $row['account_name'], $row['voucher_no'] ?? $row['journal_no'] ?? '', $row['reference_no'] ?? '', $row['description'] ?? '', $row['debit'], $row['credit'], $row['running_balance'] ?? 0];
            }
            break;
        case 'tax_summary':
            $headers = ['Item', 'Taxable Amount', 'Tax Amount'];
            $rows[] = ['Output Tax', $report['output_tax']['taxable'] ?? 0, $report['output_tax']['tax'] ?? 0];
            $rows[] = ['Input Tax', $report['input_tax']['taxable'] ?? 0, $report['input_tax']['tax'] ?? 0];
            $rows[] = ['Net Tax Payable', '', $report['net_payable'] ?? 0];
            break;
        case 'asset_register':
            $headers = ['Asset Code', 'Asset Name', 'Category', 'Acquisition Date', 'Cost', 'Accumulated Depreciation', 'NBV'];
            foreach ($report['assets'] ?? [] as $asset) {
                $rows[] = [$asset['asset_code'], $asset['asset_name'], $asset['category_name'], $asset['acquisition_date'], $asset['acquisition_cost'], $asset['accumulated_depreciation'], $asset['nbv']];
            }
            break;
        case 'budget_vs_actual':
            $headers = ['Account Code', 'Account Name', 'Department', 'Project', 'Budget', 'Actual', 'Variance', 'Utilization %'];
            foreach ($report['lines'] ?? [] as $line) {
                $rows[] = [$line['account_code'], $line['account_name'], $line['department'], $line['project'], $line['budget'], $line['actual'], $line['variance'], $line['utilization']];
            }
            break;
        case 'payroll_summary':
            $headers = ['Department', 'Runs', 'Gross', 'Deductions', 'Net', 'Employer Contributions'];
            foreach ($report['departments'] ?? [] as $dept) {
                $rows[] = [$dept['department_name'], $dept['runs'], $dept['gross'], $dept['deductions'], $dept['net'], $dept['employer_contributions'] ?? 0];
            }
            $rows[] = ['TOTALS', '', $report['totals']['gross'] ?? 0, $report['totals']['deductions'] ?? 0, $report['totals']['net'] ?? 0, $report['totals']['employer_contributions'] ?? 0];
            break;
        default:
            $headers = ['Message'];
            $rows[] = ['Export serializer not configured for this report type'];
    }

    return [$headers, $rows];
}

function exportCSV($report, $reportType, $companyName, $fyId, $filename) {
    [$headers, $rows] = normalizeReportForExport($report, $reportType);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, [$companyName]);
    fputcsv($output, [ucwords(str_replace('_', ' ', $reportType)) . ' Report']);
    fputcsv($output, ['Generated: ' . date('Y-m-d H:i:s')]);
    fputcsv($output, []);
    fputcsv($output, $headers);
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

function buildExportHtml($report, $reportType, $companyName) {
    [$headers, $rows] = normalizeReportForExport($report, $reportType);
    $title = ucwords(str_replace('_', ' ', $reportType)) . ' Report';

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #111827; font-size: 12px; }
        .header { text-align: center; margin-bottom: 18px; }
        .company { font-size: 22px; font-weight: bold; }
        .title { font-size: 16px; margin-top: 6px; }
        .meta { color: #6b7280; font-size: 11px; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #d1d5db; padding: 6px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; }
        td.num { text-align: right; }
    </style></head><body>';
    $html .= '<div class="header"><div class="company">' . htmlspecialchars($companyName) . '</div><div class="title">' . htmlspecialchars($title) . '</div><div class="meta">Generated: ' . date('Y-m-d H:i:s') . '</div></div>';
    $html .= '<table><thead><tr>';
    foreach ($headers as $header) {
        $html .= '<th>' . htmlspecialchars((string)$header) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $class = is_numeric($cell) ? ' class="num"' : '';
            $html .= '<td' . $class . '>' . htmlspecialchars((string)$cell) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table></body></html>';

    return $html;
}

function exportExcel($report, $reportType, $companyName, $fyId, $filename) {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo buildExportHtml($report, $reportType, $companyName);
    exit;
}

function exportPDF($report, $reportType, $companyName, $fyId, $filename) {
    $html = buildExportHtml($report, $reportType, $companyName);
    $autoload = dirname(__DIR__, 4) . '/vendor/autoload.php';

    if (file_exists($autoload)) {
        require_once $autoload;
    }

    if (class_exists('\\Dompdf\\Dompdf')) {
        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $dompdf->output();
    } else {
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/\\.pdf$/', '.html', $filename) . '"');
        echo $html;
    }
    exit;
}
?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Export Reports</h1>
        <p class="text-gray-600">Download financial reports in PDF, Excel, or CSV format</p>
    </div>
    <a href="../dashboard/index.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-arrow-left mr-2"></i>Dashboard</a>
</div>

<!-- Export Options -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php 
    $exportOptions = [
        ['type' => 'trial_balance', 'name' => 'Trial Balance', 'desc' => 'Debit and Credit totals by account'],
        ['type' => 'profit_loss', 'name' => 'Profit & Loss', 'desc' => 'Revenue, expenses, and net profit'],
        ['type' => 'balance_sheet', 'name' => 'Balance Sheet', 'desc' => 'Assets, liabilities, and equity'],
        ['type' => 'cash_flow', 'name' => 'Cash Flow Statement', 'desc' => 'Operating, investing, financing'],
        ['type' => 'general_ledger', 'name' => 'General Ledger', 'desc' => 'Detailed GL transactions'],
        ['type' => 'ar_aging', 'name' => 'AR Aging', 'desc' => 'Accounts receivable aging'],
        ['type' => 'ap_aging', 'name' => 'AP Aging', 'desc' => 'Accounts payable aging'],
        ['type' => 'tax_summary', 'name' => 'Tax Summary', 'desc' => 'Output tax, input tax, net payable'],
        ['type' => 'asset_register', 'name' => 'Asset Register', 'desc' => 'Fixed asset details'],
        ['type' => 'budget_vs_actual', 'name' => 'Budget vs Actual', 'desc' => 'Budget variance analysis'],
        ['type' => 'payroll_summary', 'name' => 'Payroll Summary', 'desc' => 'Payroll costs by department'],
    ];
    
    foreach ($exportOptions as $opt): 
    ?>
    <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition">
        <h3 class="font-semibold text-gray-800 mb-2"><?= sanitize($opt['name']) ?></h3>
        <p class="text-sm text-gray-600 mb-4"><?= sanitize($opt['desc']) ?></p>
        
        <div class="flex gap-2">
            <a href="?export=1&report_type=<?= $opt['type'] ?>&format=csv&financial_year_id=<?= $fyId ?>" 
               class="flex-1 bg-blue-600 text-white text-center py-2 rounded hover:bg-blue-700 text-sm">
                <i class="fas fa-file-csv mr-1"></i> CSV
            </a>
            <a href="?export=1&report_type=<?= $opt['type'] ?>&format=excel&financial_year_id=<?= $fyId ?>" 
               class="flex-1 bg-green-600 text-white text-center py-2 rounded hover:bg-green-700 text-sm">
                <i class="fas fa-file-excel mr-1"></i> Excel
            </a>
            <a href="?export=1&report_type=<?= $opt['type'] ?>&format=pdf&financial_year_id=<?= $fyId ?>" 
               class="flex-1 bg-red-600 text-white text-center py-2 rounded hover:bg-red-700 text-sm">
                <i class="fas fa-file-pdf mr-1"></i> PDF
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filter Settings -->
<div class="bg-white rounded-lg shadow mt-6 p-4">
    <h3 class="font-semibold text-gray-800 mb-4">Export Settings</h3>
    <form method="GET" class="flex flex-wrap gap-4 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Financial Year</label>
            <select name="financial_year_id" class="px-3 py-2 border rounded" onchange="this.form.submit()">
                <?php foreach ($financialYears as $fy): ?>
                <option value="<?= $fy['id'] ?>" <?= $fyId == $fy['id'] ? 'selected' : '' ?>><?= sanitize($fy['year_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<!-- Export Information -->
<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-6">
    <div class="flex items-start">
        <i class="fas fa-info-circle text-blue-600 mt-1 mr-3"></i>
        <div>
            <h4 class="font-semibold text-blue-800">Export Information</h4>
            <ul class="text-sm text-blue-700 mt-2 space-y-1">
                <li>• <strong>CSV:</strong> Compatible with spreadsheet applications, easy to import</li>
                <li>• <strong>Excel:</strong> Formatted spreadsheet with company branding</li>
                <li>• <strong>PDF:</strong> Print-ready format for documentation</li>
                <li>• All exports include company name, report title, and generation timestamp</li>
                <li>• Exports respect current permission settings and data access controls</li>
            </ul>
        </div>
    </div>
</div>

<?php require_once '../../../../includes/footer.php'; ?>
