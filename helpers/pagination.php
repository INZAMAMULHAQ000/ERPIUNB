<?php

function paginate($total, $perPage = 10, $currentPage = 1)
{
    $total = (int) $total;
    $perPage = max(1, (int) $perPage);

    $totalPages = (int) ceil($total / $perPage);

    if ($totalPages < 1) {
        $totalPages = 1;
    }

    $currentPage = max(
        1,
        min((int) $currentPage, $totalPages)
    );

    $offset = ($currentPage - 1) * $perPage;

    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}


/**
 * Get current page number
 */
function getPageNumber()
{
    $page = $_GET['page'] ?? 1;

    // Only allow numeric page values
    if (!is_numeric($page)) {
        $page = 1;
    }

    return max(1, (int) $page);
}


/**
 * Generate pagination links
 *
 * Existing GET filters are automatically preserved.
 *
 * Examples:
 *   paginationLinks($pagination, '?')
 *   paginationLinks($pagination, 'index.php')
 */
function paginationLinks($pagination, $baseUrl = '?')
{
    if (empty($pagination)) {
        return '';
    }

    $currentPage = (int) $pagination['current_page'];
    $totalPages  = (int) $pagination['total_pages'];
    $perPage     = (int) $pagination['per_page'];
    $total       = (int) $pagination['total'];

    /*
     * Calculate Showing X to Y
     */
    if ($total === 0) {
        $start = 0;
        $end = 0;
    } else {
        $start = (($currentPage - 1) * $perPage) + 1;
        $end = min($currentPage * $perPage, $total);
    }

    $html = '<div class="flex justify-between items-center mt-4">';

    /*
     * Showing text
     */
    $html .= '<div class="text-sm text-gray-600">';
    $html .= 'Showing ' . $start . ' to ' . $end . ' of ' . $total;
    $html .= '</div>';

    /*
     * Pagination buttons
     */
    if ($totalPages > 1) {

        $html .= '<div class="flex gap-1">';

        /*
         * Get current filters/search parameters
         */
        $queryParams = $_GET;

        /*
         * Remove old page.
         * We will add the correct page below.
         */
        unset($queryParams['page']);

        /*
         * Previous button
         */
        if ($currentPage > 1) {

            $queryParams['page'] = $currentPage - 1;

            $query = http_build_query($queryParams);

            $url = buildPaginationUrl($baseUrl, $query);

            $html .= '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"';
            $html .= ' class="px-3 py-1 border rounded bg-white text-gray-700 hover:bg-gray-100">';
            $html .= '&laquo;';
            $html .= '</a>';
        }

        /*
         * Page numbers
         */
        for ($i = 1; $i <= $totalPages; $i++) {

            $queryParams['page'] = $i;

            $query = http_build_query($queryParams);

            $url = buildPaginationUrl($baseUrl, $query);

            $active = ($i === $currentPage)
                ? 'bg-blue-600 text-white'
                : 'bg-white text-gray-700 hover:bg-gray-100';

            $html .= '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"';
            $html .= ' class="px-3 py-1 border rounded ' . $active . '">';
            $html .= $i;
            $html .= '</a>';
        }

        /*
         * Next button
         */
        if ($currentPage < $totalPages) {

            $queryParams['page'] = $currentPage + 1;

            $query = http_build_query($queryParams);

            $url = buildPaginationUrl($baseUrl, $query);

            $html .= '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"';
            $html .= ' class="px-3 py-1 border rounded bg-white text-gray-700 hover:bg-gray-100">';
            $html .= '&raquo;';
            $html .= '</a>';
        }

        $html .= '</div>';
    }

    $html .= '</div>';

    return $html;
}


/**
 * Build correct pagination URL
 */
function buildPaginationUrl($baseUrl, $query)
{
    /*
     * If base URL is simply '?'
     */
    if ($baseUrl === '?') {
        return '?' . $query;
    }

    /*
     * If base URL already contains ?
     */
    if (strpos($baseUrl, '?') !== false) {
        return rtrim($baseUrl, '?&') . '&' . $query;
    }

    /*
     * Normal URL
     */
    return $baseUrl . '?' . $query;
}
