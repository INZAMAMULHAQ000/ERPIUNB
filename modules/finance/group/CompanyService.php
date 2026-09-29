<?php
/**
 * F13 — Company access control (STEP 33)
 * A finance user for Company A must not automatically see Company B.
 * Super Admins bypass per-company grants.
 */

class CompanyService
{
    public static function log($db, $action, $companyId = null, $refId = null, $details = null, $reason = null, $userId = null)
    {
        $db->prepare("INSERT INTO finance_group_audit (action, company_id, reference_id, details, reason, ip_address, user_id)
                      VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$action, $companyId, $refId, $details, $reason,
                       $_SERVER['REMOTE_ADDR'] ?? null, $userId ?? ($_SESSION['user_id'] ?? null)]);
    }

    /** Is the user a Super Admin (role id 1)? */
    public static function isSuperAdmin($db, $userId)
    {
        $s = $db->prepare("SELECT COUNT(*) FROM user_roles WHERE user_id=? AND role_id=1");
        $s->execute([(int)$userId]);
        return (int)$s->fetchColumn() > 0;
    }

    /** STEP 33 — explicit per-company permission check. */
    public static function canAccessCompany($db, $userId, $companyId)
    {
        if (self::isSuperAdmin($db, $userId)) return true;
        $s = $db->prepare("SELECT COUNT(*) FROM user_companies WHERE user_id=? AND company_id=?");
        $s->execute([(int)$userId, (int)$companyId]);
        return (int)$s->fetchColumn() > 0;
    }

    /** Companies the user may see (for dropdown filtering etc.). */
    public static function accessibleCompanies($db, $userId)
    {
        if (self::isSuperAdmin($db, $userId))
            return $db->query("SELECT id, company_code, company_name FROM companies WHERE status='active' ORDER BY company_name")->fetchAll();
        $s = $db->prepare("SELECT c.id, c.company_code, c.company_name FROM user_companies uc
                           JOIN companies c ON c.id=uc.company_id WHERE uc.user_id=? AND c.status='active'
                           ORDER BY c.company_name");
        $s->execute([(int)$userId]);
        return $s->fetchAll();
    }

    /** Deny helper for pages: throws when company out of scope (catchable in tests/CLI). */
    public static function requireCompanyAccess($db, $userId, $companyId)
    {
        if (!self::canAccessCompany($db, $userId, $companyId)) {
            self::log($db, 'company_access_denied', (int)$companyId, null,
                'User #' . (int)$userId . ' blocked', null, $userId);
            throw new Exception('Access Denied — no permission for this company');
        }
    }
}
