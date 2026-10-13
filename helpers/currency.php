<?php
/**
 * Currency resolution & formatting.
 *
 * Currencies and FX rates come from the `currencies` / `currency_rates` tables
 * and are editable in the UI — nothing here is hardcoded. The base currency is
 * the active company's, falling back to app_settings.
 *
 * Function signatures are unchanged so existing call sites keep working.
 */

require_once __DIR__ . '/company.php';

if (!defined('BASE_CURRENCY')) {
    // Retained only as a last-resort default for existing `= BASE_CURRENCY`
    // parameter declarations; getBaseCurrency() is the real source.
    define('BASE_CURRENCY', 'USD');
}

if (!function_exists('getBaseCurrency')) {
    /** The currency amounts are stored in: active company first, then settings. */
    function getBaseCurrency(): string {
        static $cached = null;
        if ($cached !== null) { return $cached; }
        try {
            $company = activeCompany();
            if ($company && !empty($company['base_currency'])) {
                return $cached = strtoupper($company['base_currency']);
            }
            $s = getDB()->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'base_currency'");
            $s->execute();
            $v = $s->fetchColumn();
            if ($v) { return $cached = strtoupper((string) $v); }
        } catch (Throwable $e) { /* fall through */ }
        return $cached = BASE_CURRENCY;
    }
}

if (!function_exists('getCurrencies')) {
    /** Active currencies with their most recently effective rate. */
    function getCurrencies(): array {
        static $cache = null;
        if ($cache !== null) { return $cache; }

        $list = [];
        try {
            $rows = getDB()->query("
                SELECT c.code, c.name, c.symbol, c.decimal_places,
                       (SELECT cr.rate_to_base FROM currency_rates cr
                         WHERE cr.currency_code = c.code
                         ORDER BY cr.effective_date DESC, cr.id DESC LIMIT 1) AS rate
                FROM currencies c
                WHERE c.is_active = 1
                ORDER BY c.sort_order ASC, c.code ASC
            ")->fetchAll();

            foreach ($rows as $r) {
                $list[$r['code']] = [
                    'name'     => $r['name'],
                    'symbol'   => $r['symbol'],
                    'rate'     => $r['rate'] !== null ? (float) $r['rate'] : 1.0,
                    'decimals' => (int) $r['decimal_places'],
                ];
            }
        } catch (Throwable $e) {
            error_log('currency lookup failed: ' . $e->getMessage());
        }

        if ($list === []) {
            // Never return empty: the navbar and money formatters index into this.
            $base = getBaseCurrency();
            $list[$base] = ['name' => $base, 'symbol' => $base . ' ', 'rate' => 1.0, 'decimals' => 2];
        }
        return $cache = $list;
    }
}

if (!function_exists('getCurrencyInfo')) {
    /** Safe accessor — returns a sane entry for an unknown or missing code. */
    function getCurrencyInfo(?string $code): array {
        $all = getCurrencies();
        $code = $code !== null ? strtoupper($code) : '';
        if (isset($all[$code])) { return $all[$code]; }
        $base = getBaseCurrency();
        return $all[$base] ?? reset($all);
    }
}

if (!function_exists('getActiveCurrency')) {
    function getActiveCurrency(): string {
        $session = isset($_SESSION['active_currency']) ? strtoupper((string) $_SESSION['active_currency']) : '';
        if ($session !== '' && isset(getCurrencies()[$session])) { return $session; }
        return getBaseCurrency();
    }
}

if (!function_exists('setActiveCurrency')) {
    function setActiveCurrency(string $code): void {
        $code = strtoupper($code);
        if (isset(getCurrencies()[$code])) {
            $_SESSION['active_currency'] = $code;
        }
    }
}

if (!function_exists('convertAmount')) {
    function convertAmount(float $amount, ?string $from = null, ?string $to = null): float {
        $from = $from ?? getBaseCurrency();
        $to   = $to   ?? getActiveCurrency();
        $from = strtoupper($from);
        $to   = strtoupper($to);
        if ($from === $to) { return $amount; }

        $currencies = getCurrencies();
        $fromRate = isset($currencies[$from]) ? (float) $currencies[$from]['rate'] : null;
        $toRate   = isset($currencies[$to])   ? (float) $currencies[$to]['rate']   : null;
        if ($fromRate === null || $toRate === null || $fromRate == 0.0) {
            return $amount;   // unmapped rate: show the stored figure rather than a wrong one
        }
        return $amount * ($toRate / $fromRate);
    }
}

if (!function_exists('formatMoney')) {
    function formatMoney(float $amount, $decimals = null, ?string $currency = null): string {
        if ($currency === null && is_string($decimals)) {
            $currency = $decimals;
            $decimals = null;
        }
        $currency = $currency ? strtoupper($currency) : getActiveCurrency();
        $info = getCurrencyInfo($currency);

        $base = getBaseCurrency();
        if ($currency !== $base) {
            $amount = convertAmount($amount, $base, $currency);
        }

        $d = $decimals !== null ? (int) $decimals : (int) $info['decimals'];
        return $info['symbol'] . number_format($amount, $d);
    }
}

if (!function_exists('formatAmount')) {
    function formatAmount(float $amount, ?int $decimals = null, ?string $currency = null): string {
        return formatMoney($amount, $decimals, $currency);
    }
}
