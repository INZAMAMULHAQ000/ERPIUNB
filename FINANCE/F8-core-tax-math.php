<?php
// Quick CLI validation of TaxService calculations (F8 STEP 22 core math)
error_reporting(E_ALL);
ini_set('display_errors', '1');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SESSION = ['user_id' => 1, 'user_name' => 'System'];
define('_TAX_CORE_TEST_', true);

require_once 'C:\xampp\htdocs\ERP\config\database.php';
require_once 'C:\xampp\htdocs\ERP\modules\finance\tax\TaxService.php';

function check($label, $cond, $extra = '') {
    echo str_pad($cond ? 'PASS' : 'FAIL', 5) . "  $label";
    if ($extra !== '') echo "   [$extra]";
    echo "\n";
    if (!$cond) global $fail; $fail = $fail ?? 0; if (!$cond) ${'fail'}++;
}

$fail = 0;

// 5% exclusive: net 1000 -> tax 50, total 1050
$c = TaxService::calculateLineTax(1000, ['tax_rate_id' => 1]);   // VAT 5%
check('5% exclusive tax = 50', $c['tax_amount'] == 50, json_encode($c));
check('5% exclusive net = 1000', $c['net_amount'] == 1000);
check('5% exclusive total = 1050', $c['total_amount'] == 1050);

// 5% inclusive: gross 1050 -> tax 50, net 1000
$c = TaxService::calculateLineTax(1050, ['tax_rate_id' => 1, 'inclusive' => true]);
check('5% inclusive tax = 50', abs($c['tax_amount'] - 50) < 0.01, json_encode($c));
check('5% inclusive net = 1000', abs($c['net_amount'] - 1000) < 0.01);
check('5% inclusive total = 1050', $c['total_amount'] == 1050);

// Zero-rated: tax = 0, still taxable flow
$z = TaxService::getApplicableTaxRate(1, date('Y-m-d'), null); // VAT default 5%
$c = TaxService::calculateLineTax(500, ['tax_rate_id' => null, 'tax_type_id' => 1, 'tax_status' => 'zero_rated']);
check('zero-rated tax = 0', $c['tax_amount'] == 0, json_encode(['pct' => $c['rate_percentage'], 'tax' => $c['tax_amount']]));

// Exempt: tax = 0, exemption retained
$c = TaxService::calculateLineTax(500, ['tax_type_id' => 1, 'tax_status' => 'exempt', 'exemption_id' => 1]);
check('exempt tax = 0', $c['tax_amount'] == 0, json_encode($c));
check('exempt exemption_id retained', $c['exemption_id'] == 1);

// Historical rate: pinned rate id
$c = TaxService::calculateLineTax(1000, ['tax_rate_id' => 1]); // pinned to 5%
check('historical pinned rate 5%', $c['rate_percentage'] == 5, json_encode($c));

// 18% GST standard
$c = TaxService::calculateLineTax(2000, ['tax_rate_id' => 4]); // GST 18%
check('GST 18% tax = 360', $c['tax_amount'] == 360, json_encode($c));

// Multiple tax lines document aggregate
$doc = TaxService::calculateDocumentTax([
    ['taxable_amount' => 1000, 'tax_rate_id' => 1],   // 5%  -> 50
    ['taxable_amount' => 2000, 'tax_rate_id' => 4],   // 18% -> 360
    ['taxable_amount' => 300,  'tax_type_id' => 1, 'tax_status' => 'zero_rated'], // 0
]);
check('document net = 3300', $doc['net_total'] == 3300, json_encode($doc['net_total'] ?? null));
check('document tax = 410', abs($doc['tax_total'] - 410) < 0.01, json_encode($doc['tax_total'] ?? null));
check('document gross = 3710', abs($doc['gross_total'] - 3710) < 0.01);
check('document rate breakdown = 2 rates', count($doc['rates']) == 2, json_encode($doc['rates']));

// Rounding: odd base 99.995 * 5% -> tax 5.00, total 105.00
$c = TaxService::calculateLineTax(99.995, ['tax_rate_id' => 1, 'inclusive' => false]);
check('rounding tax 5.00', $c['tax_amount'] == 5.00, json_encode($c));
check('rounding total 105.00', $c['total_amount'] == 105.00);
check('roundAmount 4.99975 -> 5.00', TaxService::roundAmount(4.99975) == 5.00);

echo "\n" . ($fail === 0 ? "CORE TAX MATH: ALL PASS ✅" : "$fail FAILURES ❌") . "\n";