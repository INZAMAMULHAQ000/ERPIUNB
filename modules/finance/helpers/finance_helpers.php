<?php
/**
 * Finance Helper Functions
 * Provides common formatting and utility functions for finance module
 */

if (!function_exists('formatAmount')) {
    function formatAmount($value = 0) {
        return number_format($value, 2, '.', ',');
    }
}

if (!function_exists('formatCurrency')) {
    function formatCurrency($currency = 'AED') {
        $currencies = [
            'AED' => 'AED ',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
        ];
        return $currencies[$currency] ?? '';
    }
}

if (!function_exists('formatNumber')) {
    function formatNumber($value) {
        return number_format($value, 2, '.', ',');
    }
}

if (!function_exists('formatDate')) {
    function formatDate($date) {
        if ($date == null || $date == '') {
            return '';
        }
        return date('M d, Y', strtotime($date));
    }
}

if (!function_exists('formatMoney')) {
    function formatMoney($value, $currency = 'AED') {
        return formatCurrency($currency) . formatNumber($value);
    }
}

if (!function_exists('sanitize')) {
    function sanitize($value) {
        return htmlspecialchars(strip_tags($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('getActiveFinancialYear')) {
    function getActiveFinancialYear($db) {
        $stmt = $db->prepare('SELECT * FROM financial_years WHERE status = ? ORDER BY start_date DESC, id DESC LIMIT 1');
        $stmt->execute(['active']);
        return $stmt->fetch();
    }
}

if (!function_exists('getFinancialYearForDate')) {
    function getFinancialYearForDate($db, $date, $companyId = null, $requireActive = true) {
        $sql = 'SELECT * FROM financial_years WHERE ? BETWEEN start_date AND end_date';
        $params = [$date];
        if ($companyId !== null) {
            $sql .= ' AND company_id = ?';
            $params[] = (int)$companyId;
        }
        if ($requireActive) {
            $sql .= ' AND status = ?';
            $params[] = 'active';
        }
        $sql .= ' ORDER BY CASE WHEN status = "active" THEN 0 ELSE 1 END, start_date DESC, id DESC LIMIT 1';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }
}

if (!function_exists('paginate')) {
    function paginate($totalItems, $perPage, $currentPage) {
        $currentPage = max($currentPage, 1);
        $totalPages = ceil($totalItems / $perPage);
        $currentPage = min($currentPage, $totalPages);

        $startItem = ($currentPage - 1) * $perPage;
        $endItem = min($startItem + $perPage, $totalItems);

        $pagination = [
            'totalItems' => $totalItems,
            'totalPages' => $totalPages,
            'currentPage' => $currentPage,
            'startItem' => $startItem,
            'endItem' => $endItem,
            'hasPrevious' => $currentPage > 1,
            'hasNext' => $currentPage < $totalPages,
            'prevPage' => $currentPage - 1,
            'nextPage' => $currentPage + 1,
            'offset' => ($currentPage - 1) * $perPage,
        ];

        return $pagination;
    }
}

if (!function_exists('url')) {
    function url($path = '') {
        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('hasPermission')) {
    function hasPermission($permission) {
        // Check if user has the specified permission
        // This should be implemented based on the RBAC system
        return isset($_SESSION['user_role']) && in_array($permission, $_SESSION['user_role'] ?? []);
    }
}

if (!function_exists('redirect_to')) {
    function redirect_to($url) {
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('getMonthName')) {
    function getMonthName($monthNum) {
        $months = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];
        return $months[(int)$monthNum] ?? '';
    }
}

if (!function_exists('getStatusBadge')) {
    function getStatusBadge($status) {
        $status = strtolower($status);
        $map = [
            'posted'    => 'bg-green-100 text-green-800',
            'draft'     => 'bg-yellow-100 text-yellow-800',
            'pending'   => 'bg-yellow-100 text-yellow-800',
            'approved'  => 'bg-blue-100 text-blue-800',
            'paid'      => 'bg-green-100 text-green-800',
            'failed'    => 'bg-red-100 text-red-800',
            'reversed'  => 'bg-red-100 text-red-800',
            'cancelled' => 'bg-gray-100 text-gray-800',
            'reconciled'=> 'bg-green-100 text-green-800',
            'unreconciled'=> 'bg-red-100 text-red-800',
            'investigation'=> 'bg-orange-100 text-orange-800',
        ];
        return $map[$status] ?? 'bg-gray-100 text-gray-800';
    }
}

/* ADD THIS */
if (!function_exists('formatVoucherStatus')) {
    function formatVoucherStatus($status)
    {
        $status = strtolower(trim((string)$status));

        $map = [
            'draft' => [
                'label' => 'Draft',
                'class' => 'bg-yellow-100 text-yellow-800'
            ],
            'posted' => [
                'label' => 'Posted',
                'class' => 'bg-green-100 text-green-800'
            ],
            'reversed' => [
                'label' => 'Reversed',
                'class' => 'bg-red-100 text-red-800'
            ],
            'cancelled' => [
                'label' => 'Cancelled',
                'class' => 'bg-gray-100 text-gray-800'
            ],
            'pending' => [
                'label' => 'Pending',
                'class' => 'bg-blue-100 text-blue-800'
            ]
        ];

        $item = $map[$status] ?? [
            'label' => ucfirst($status),
            'class' => 'bg-gray-100 text-gray-800'
        ];

        return '<span class="inline-flex items-center px-3 py-1 rounded text-xs font-semibold '
            . $item['class']
            . '">'
            . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8')
            . '</span>';
    }
}

if (!function_exists('formatPercent')) {
    function formatPercent($value, $total) {
        if ($total == 0) return '0.00%';
        return number_format(($value / $total) * 100, 2) . '%';
    }
}
