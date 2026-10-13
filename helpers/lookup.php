<?php
/**
 * Lookup tables — the single source for enumerations that used to be hardcoded
 * PHP arrays in the customer and company forms.
 *
 * Every accessor takes a $fallback so a page still renders if a lookup type has
 * not been seeded in an environment yet.
 */

require_once __DIR__ . '/security.php';

if (!function_exists('lookupOptions')) {
    /** [key => label] for an active lookup type, ordered by sort_order. */
    function lookupOptions($typeKey, array $fallback = []) {
        static $cache = [];
        $key = $typeKey;
        if (isset($cache[$key])) { return $cache[$key]; }
        $out = [];
        try {
            $s = getDB()->prepare("
                SELECT value_key, value_label FROM lookup_values
                WHERE type_key = ? AND is_active = 1
                ORDER BY sort_order ASC, value_label ASC
            ");
            $s->execute([$typeKey]);
            foreach ($s->fetchAll() as $r) { $out[$r['value_key']] = $r['value_label']; }
        } catch (Throwable $e) {
            error_log('lookupOptions failed for ' . $typeKey . ': ' . $e->getMessage());
        }
        if ($out === []) { $out = $fallback; }
        return $cache[$key] = $out;
    }
}

if (!function_exists('lookupExtra')) {
    /** Decode the JSON `extra` column for one value, e.g. ['days' => 30]. */
    function lookupExtra($typeKey, $valueKey) {
        static $cache = [];
        $ck = $typeKey . '|' . $valueKey;
        if (isset($cache[$ck])) { return $cache[$ck]; }
        try {
            $s = getDB()->prepare("SELECT extra FROM lookup_values WHERE type_key = ? AND value_key = ? AND is_active = 1");
            $s->execute([$typeKey, $valueKey]);
            $raw = $s->fetchColumn();
            return $cache[$ck] = ($raw ? (json_decode((string) $raw, true) ?: []) : []);
        } catch (Throwable $e) {
            return $cache[$ck] = [];
        }
    }
}

if (!function_exists('paymentTermDays')) {
    /** Days represented by a payment-terms label, or null if unknown. */
    function paymentTermDays($label) {
        $extra = lookupExtra('trade.payment_terms', (string) $label);
        if (isset($extra['days'])) { return (int) $extra['days']; }
        // Unknown label: parse "Net 45" style so a hand-typed term still works.
        if (preg_match('/net\s+(\d+)/i', (string) $label, $m)) { return (int) $m[1]; }
        if (stripos((string) $label, 'due on receipt') === 0) { return 0; }
        return null;
    }
}

if (!function_exists('lookupLabel')) {
    /** Human label for a stored key; falls back to a de-humanised key. */
    function lookupLabel($typeKey, $valueKey, array $fallback = []) {
        if ($valueKey === null || $valueKey === '') { return null; }
        $opts = lookupOptions($typeKey, $fallback);
        if (isset($opts[$valueKey])) { return $opts[$valueKey]; }
        return ucwords(str_replace('_', ' ', (string) $valueKey));
    }
}
