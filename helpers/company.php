<?php
/**
 * Active company context.
 *
 * Resolves the company the current request operates against. Nothing here is
 * hardcoded: the session value wins, otherwise the company flagged
 * `is_default` is used, otherwise the lowest active company id.
 */

require_once __DIR__ . '/security.php';

if (!function_exists('appSetting')) {
    /** Typed accessor for the app_settings table. Cached per request. */
    function appSetting($key, $default = null) {
        static $cache = [];
        if (array_key_exists($key, $cache)) { return $cache[$key]; }
        try {
            $s = getDB()->prepare("SELECT setting_value, value_type FROM app_settings WHERE setting_key = ?");
            $s->execute([$key]);
            $row = $s->fetch();
            if (!$row || $row['setting_value'] === null) { return $cache[$key] = $default; }
            $v = $row['setting_value'];
            $out = match ($row['value_type']) {
                'boolean' => in_array(strtolower((string) $v), ['1', 'true', 'yes'], true),
                'integer' => (int) $v,
                'decimal' => (float) $v,
                'json'    => json_decode((string) $v, true),
                default   => (string) $v,
            };
            return $cache[$key] = $out;
        } catch (Throwable $e) {
            return $cache[$key] = $default;
        }
    }
}

if (!function_exists('appName')) {
    function appName() {
        return appSetting('app_name', 'ERP System') ?: 'ERP System';
    }
}

if (!function_exists('activeCompany')) {
    /** Full companies row for the active company, or null if none is configured. */
    function activeCompany() {
        static $cache = null;
        if ($cache !== null) { return $cache; }

        $db = getDB();
        $uid = (int) ($_SESSION['user_id'] ?? 0);
        $wanted = (int) ($_SESSION['active_company_id'] ?? 0);

        $sql = "
            SELECT * FROM companies c
            WHERE c.deleted_at IS NULL AND c.status = 'active'
              AND (
                    EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id = ? AND ur.role_id = 1)
                 OR EXISTS (SELECT 1 FROM user_companies uc WHERE uc.user_id = ? AND uc.company_id = c.id)
              )
        ";
        $params = [$uid, $uid];

        $order = [];
        if ($wanted > 0) {
            // Rank the user's chosen company first without excluding the others,
            // so an inaccessible or deleted preference degrades to the default.
            $order[] = '(c.id = ?) DESC';
            $params[] = $wanted;
        }
        $order[] = 'c.is_default DESC';
        $order[] = 'c.id ASC';
        $sql .= ' ORDER BY ' . implode(', ', $order) . ' LIMIT 1';

        // The preference only applies when the user may actually use that company,
        // so fall back through is_default rather than leaking an inaccessible one.
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: null;

        if ($row && $wanted > 0 && (int) $row['id'] !== $wanted) {
            unset($_SESSION['active_company_id']);
        }
        if ($row) {
            $_SESSION['active_company_id'] = (int) $row['id'];
        }
        $cache = $row;
        return $cache;
    }
}

if (!function_exists('activeCompanyId')) {
    function activeCompanyId() {
        $c = activeCompany();
        return $c ? (int) $c['id'] : 0;
    }
}

if (!function_exists('activeCompanyCurrency')) {
    function activeCompanyCurrency($fallback = null) {
        $c = activeCompany();
        if ($c) { return $c['base_currency']; }
        if ($fallback !== null) { return $fallback; }
        $s = getDB()->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'base_currency'");
        $s->execute();
        return $s->fetchColumn() ?: 'USD';
    }
}

if (!function_exists('companyFilterSql')) {
    /**
     * SQL fragment restricting rows to the active company.
     * $allowNull keeps shared rows (e.g. a group chart of accounts) visible.
     */
    function companyFilterSql($column, &$params, $allowNull = false) {
        $id = activeCompanyId();
        if ($id <= 0) { return ''; }
        $params[] = $id;
        return $allowNull
            ? " ($column IS NULL OR $column = ?)"
            : " $column = ?";
    }
}

if (!function_exists('nextDocumentNumber')) {
    /**
     * Allocation-safe document number. Uses a persisted per-company/per-type
     * counter instead of COUNT(*)+1, which collides under concurrency and leaves
     * gaps when rows are deleted.
     */
    function nextDocumentNumber($db, $docType, $periodKey = '') {
        $companyId = activeCompanyId();
        if ($companyId <= 0) {
            throw new RuntimeException('No active company — cannot allocate a document number.');
        }

        $stmt = $db->prepare("SELECT prefix, padding, next_number FROM document_sequences WHERE company_id = ? AND doc_type = ?");
        $stmt->execute([$companyId, $docType]);
        $seq = $stmt->fetch();

        if (!$seq) {
            $prefix = $db->prepare("SELECT COALESCE(NULLIF(invoice_prefix,''), 'DOC') FROM companies WHERE id = ?");
            $prefix->execute([$companyId]);
            $db->prepare("INSERT INTO document_sequences (company_id, doc_type, prefix, padding, next_number) VALUES (?,?,?,?,1)")
               ->execute([$companyId, $docType, $prefix->fetchColumn() ?: 'DOC', 4]);
            $seq = ['prefix' => 'DOC', 'padding' => 4, 'next_number' => 1];
        }

        // Lock the counter row so two concurrent postings cannot take the same number.
        $upd = $db->prepare("UPDATE document_sequences SET next_number = next_number + 1 WHERE company_id = ? AND doc_type = ?");
        $upd->execute([$companyId, $docType]);

        $number = $seq['prefix'];
        if ($periodKey !== '') { $number .= $periodKey; }
        $number .= str_pad((string) $seq['next_number'], (int) $seq['padding'], '0', STR_PAD_LEFT);
        return $number;
    }
}
