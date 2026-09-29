<?php
/**
 * Shared Procurement P1 bootstrap.
 *
 * All module pages load application dependencies from absolute filesystem
 * paths so their behavior does not depend on the current working directory.
 */

if (!defined('PROCUREMENT_MODULE_DIR')) {
    define('PROCUREMENT_MODULE_DIR', __DIR__);
}

if (!defined('PROCUREMENT_APP_ROOT')) {
    define('PROCUREMENT_APP_ROOT', dirname(__DIR__, 2));
}

require_once PROCUREMENT_APP_ROOT . '/includes/header.php';
require_once PROCUREMENT_APP_ROOT . '/modules/finance/helpers/finance_helpers.php';
require_once PROCUREMENT_MODULE_DIR . '/ProcurementService.php';
require_once PROCUREMENT_MODULE_DIR . '/BillService.php';

/** Return normalized company IDs visible to the current user. */
function procurementAccessibleCompanyIds($db, $userId): array
{
    $companies = CompanyService::accessibleCompanies($db, (int)$userId);
    $ids = array_map(static fn($company) => (int)$company['id'], $companies);
    return array_values(array_unique(array_filter($ids)));
}

/** Build a prepared-statement IN clause for an already trusted column name. */
function procurementCompanyScopeSql(array $companyIds, string $column = 'pr.company_id'): string
{
    if (!$companyIds) {
        return '1=0';
    }

    return $column . ' IN (' . implode(',', array_fill(0, count($companyIds), '?')) . ')';
}
