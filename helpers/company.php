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
     * Allocation-safe document number.
     *
     * Replaces the old COUNT(*)+1 generators, which produced duplicate numbers
     * under concurrency and reused a number after any deletion. The counter row
     * is bumped and read in a single atomic UPDATE, and the rendered shape comes
     * from the sequence's own `format` template so existing numbering is preserved.
     *
     * @param string $docType  document_sequences.doc_type
     * @param string|null $periodKey  overrides the sequence's date_format period
     */
    function nextDocumentNumber($db, $docType, $periodKey = null) {
        $companyId = activeCompanyId();
        if ($companyId <= 0) {
            throw new RuntimeException('No active company — cannot allocate a document number.');
        }

        $stmt = $db->prepare("SELECT * FROM document_sequences WHERE company_id = ? AND doc_type = ? AND period_key = '' LIMIT 1");
        $stmt->execute([$companyId, $docType]);
        $base = $stmt->fetch();
        if (!$base) {
            throw new RuntimeException("No document sequence configured for '$docType'. Add it under Configuration → Document Numbering.");
        }

        // Resolve the period this number belongs to ('' = one counter for all time).
        if ($periodKey === null) {
            $periodKey = $base['date_format'] ? date($base['date_format']) : '';
        }

        // Materialise the counter row for this period, inheriting the template.
        $db->prepare("
            INSERT INTO document_sequences (company_id, doc_type, period_key, prefix, padding, date_format, format, next_number)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE document_sequences.id = document_sequences.id
        ")->execute([$companyId, $docType, $periodKey, $base['prefix'], $base['padding'], $base['date_format'], $base['format']]);

        // Single atomic read-and-increment: LAST_INSERT_ID() yields the value before the bump.
        $db->prepare("
            UPDATE document_sequences
               SET next_number = LAST_INSERT_ID(next_number) + 1
             WHERE company_id = ? AND doc_type = ? AND period_key = ?
        ")->execute([$companyId, $docType, $periodKey]);
        $allocated = (int) $db->query("SELECT LAST_INSERT_ID()")->fetchColumn();

        $fy = getCompanyFinancialYear($db, $companyId);
        $tokens = [
            '{prefix}'    => (string) $base['prefix'],
            '{seq}'       => str_pad((string) $allocated, max(1, (int) $base['padding']), '0', STR_PAD_LEFT),
            '{period}'    => $periodKey,
            '{year}'      => date('Y'),
            '{month}'     => date('m'),
            '{fy}'        => $fy ? date('Y', strtotime($fy['start_date'])) : date('Y'),
            '{fy_short}'  => $fy ? date('Y', strtotime($fy['start_date'])) . '-' . date('Y', strtotime($fy['end_date'])) : date('Y') . '-' . date('Y'),
        ];

        $format = (string) ($base['format'] ?: '{prefix}{seq}');
        return str_replace(array_keys($tokens), array_values($tokens), $format);
    }
}

if (!function_exists('peekDocumentNumber')) {
    /**
     * Render the number the next allocation WOULD produce, without consuming it.
     * Use for form previews: allocating on page view would burn a number every
     * time someone opens and abandons a create form, punching gaps into a legal
     * document sequence.
     */
    function peekDocumentNumber($db, $docType, $periodKey = null) {
        $companyId = activeCompanyId();
        if ($companyId <= 0) {
            throw new RuntimeException('No active company — cannot preview a document number.');
        }
        $stmt = $db->prepare("SELECT * FROM document_sequences WHERE company_id = ? AND doc_type = ? AND period_key = '' LIMIT 1");
        $stmt->execute([$companyId, $docType]);
        $base = $stmt->fetch();
        if (!$base) {
            throw new RuntimeException("No document sequence configured for '$docType'.");
        }
        if ($periodKey === null) {
            $periodKey = $base['date_format'] ? date($base['date_format']) : '';
        }
        $s = $db->prepare("SELECT next_number FROM document_sequences WHERE company_id = ? AND doc_type = ? AND period_key = ?");
        $s->execute([$companyId, $docType, $periodKey]);
        $next = (int) ($s->fetchColumn() ?: 1);

        $fy = getCompanyFinancialYear($db, $companyId);
        $tokens = [
            '{prefix}'   => (string) $base['prefix'],
            '{seq}'      => str_pad((string) $next, max(1, (int) $base['padding']), '0', STR_PAD_LEFT),
            '{period}'   => $periodKey,
            '{year}'     => date('Y'),
            '{month}'    => date('m'),
            '{fy}'       => $fy ? date('Y', strtotime($fy['start_date'])) : date('Y'),
            '{fy_short}' => $fy ? date('Y', strtotime($fy['start_date'])) . '-' . date('Y', strtotime($fy['end_date'])) : date('Y') . '-' . date('Y'),
        ];
        $format = (string) ($base['format'] ?: '{prefix}{seq}');
        return str_replace(array_keys($tokens), array_values($tokens), $format);
    }
}

if (!function_exists('getCompanyFinancialYear')) {
    /** The company's current financial year, or null. Never borrows another's. */
    function getCompanyFinancialYear($db, $companyId) {
        static $cache = [];
        $cid = (int) $companyId;
        if (array_key_exists($cid, $cache)) { return $cache[$cid]; }
        try {
            $s = $db->prepare("
                SELECT * FROM financial_years
                WHERE company_id = ? AND status = 'active'
                  AND CURDATE() BETWEEN start_date AND end_date
                ORDER BY start_date DESC LIMIT 1
            ");
            $s->execute([$cid]);
            $row = $s->fetch() ?: null;
            if (!$row) {
                $s2 = $db->prepare("SELECT * FROM financial_years WHERE company_id = ? AND status = 'active' ORDER BY start_date DESC LIMIT 1");
                $s2->execute([$cid]);
                $row = $s2->fetch() ?: null;
            }
            return $cache[$cid] = $row;
        } catch (Throwable $e) {
            return $cache[$cid] = null;
        }
    }
}
