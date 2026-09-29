<?php
/**
 * Currency conversion & formatting helper.
 * All amounts stored in USD (base currency).
 * Display-only conversion to selected currency.
 */

define('BASE_CURRENCY', 'USD');

function getCurrencies(): array {
    return [
        'USD' => ['name' => 'US Dollar',       'symbol' => '$',  'rate' => 1.0000, 'decimals' => 2],
        'INR' => ['name' => 'Indian Rupee',     'symbol' => '&#8377;', 'rate' => 83.5000, 'decimals' => 2],
        'AED' => ['name' => 'UAE Dirham',       'symbol' => '&#1583;.&#1573;', 'rate' => 3.6700, 'decimals' => 2],
        'EUR' => ['name' => 'Euro',             'symbol' => '&#8364;', 'rate' => 0.9200, 'decimals' => 2],
        'GBP' => ['name' => 'British Pound',    'symbol' => '&#163;', 'rate' => 0.7900, 'decimals' => 2],
        'SAR' => ['name' => 'Saudi Riyal',      'symbol' => '&#65020;', 'rate' => 3.7500, 'decimals' => 2],
        'CAD' => ['name' => 'Canadian Dollar',  'symbol' => 'CA$', 'rate' => 1.3600, 'decimals' => 2],
        'AUD' => ['name' => 'Australian Dollar','symbol' => 'AU$', 'rate' => 1.5400, 'decimals' => 2],
        'JPY' => ['name' => 'Japanese Yen',     'symbol' => '&#165;', 'rate' => 149.5000, 'decimals' => 0],
        'CNY' => ['name' => 'Chinese Yuan',     'symbol' => '&#20803;', 'rate' => 7.2400, 'decimals' => 2],
        'MYR' => ['name' => 'Malaysian Ringgit','symbol' => 'RM', 'rate' => 4.7200, 'decimals' => 2],
        'QAR' => ['name' => 'Qatari Riyal',     'symbol' => '&#65020;', 'rate' => 3.6400, 'decimals' => 2],
    ];
}

function getActiveCurrency(): string {
    return $_SESSION['active_currency'] ?? BASE_CURRENCY;
}

function setActiveCurrency(string $code): void {
    $currencies = getCurrencies();
    if (isset($currencies[strtoupper($code)])) {
        $_SESSION['active_currency'] = strtoupper($code);
    }
}

function convertAmount(float $amount, string $from = BASE_CURRENCY, ?string $to = null): float {
    $to = $to ?? getActiveCurrency();
    if ($from === $to) return $amount;
    $currencies = getCurrencies();
    $fromRate = $currencies[$from]['rate'] ?? 1;
    $toRate = $currencies[$to]['rate'] ?? 1;
    return $amount * ($toRate / $fromRate);
}

function formatMoney(float $amount, $decimals = null, ?string $currency = null): string {
    if ($currency === null && is_string($decimals)) {
        $currency = $decimals;
        $decimals = null;
    }
    $currency = $currency ?? getActiveCurrency();
    $currencies = getCurrencies();
    $info = $currencies[$currency] ?? $currencies[BASE_CURRENCY];

    if ($currency !== BASE_CURRENCY) {
        $amount = convertAmount($amount, BASE_CURRENCY, $currency);
    }

    $d = $decimals ?? $info['decimals'];
    $formatted = number_format($amount, $d);
    return $info['symbol'] . $formatted;
}

function formatAmount(float $amount, ?int $decimals = null, ?string $currency = null): string {
    return formatMoney($amount, $decimals, $currency);
}
