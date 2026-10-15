<?php

/**
 * Breadcrumb Navigation Helper
 * 
 * Generates breadcrumb navigation based on the current page path.
 * Parses the URL/path to build hierarchical navigation.
 */

// Cache for module detection to avoid repeated file checks
$_moduleCache = null;
$_subModuleCache = [];

/**
 * Get list of valid top-level module directories (those with index.php directly under /modules)
 * 
 * @return array Array of module names that have their own index.php at the top level
 */
function getModuleList()
{
    global $_moduleCache;
    
    if ($_moduleCache !== null) {
        return $_moduleCache;
    }
    
    $modulePath = dirname(__DIR__) . '/modules';
    $_moduleCache = [];
    
    if (is_dir($modulePath)) {
        $directories = scandir($modulePath);
        foreach ($directories as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }
            // Only include directories that are direct children of /modules and have index.php
            // This ensures we only get top-level parent modules like 'finance', 'projects', etc.
            if (is_dir($modulePath . '/' . $dir) && file_exists($modulePath . '/' . $dir . '/index.php')) {
                $_moduleCache[] = $dir;
            }
        }
    }
    
    return $_moduleCache;
}

/**
 * Check if a path segment represents a sub-module with its own index.php
 * e.g., /finance/customer_invoices/index.php - customer_invoices is a sub-module
 * 
 * @param string $parentModule The parent module name (e.g., 'finance')
 * @param string $segment The segment to check (e.g., 'customer_invoices')
 * @return bool True if the segment is a sub-module with its own index.php
 */
function isSubModule($parentModule, $segment)
{
    global $_subModuleCache;
    
    $cacheKey = $parentModule . '/' . $segment;
    if (isset($_subModuleCache[$cacheKey])) {
        return $_subModuleCache[$cacheKey];
    }
    
    $basePath = dirname(__DIR__) . '/modules/' . $parentModule . '/' . $segment;
    $isSubModule = is_dir($basePath) && file_exists($basePath . '/index.php');
    
    $_subModuleCache[$cacheKey] = $isSubModule;
    return $isSubModule;
}

/**
 * Generate breadcrumb items from the current request path
 * 
 * @param string|null $customPath Optional custom path to use instead of REQUEST_URI
 * @return array Array of breadcrumb items with 'label', 'url', and 'is_current' keys
 */
function generateBreadcrumbs($customPath = null)
{
    $basePath = dirname(__DIR__);
    
    // Determine the path to parse
    if ($customPath !== null) {
        $path = $customPath;
    } else {
        $path = $_SERVER['REQUEST_URI'] ?? '';
        // Remove query string and base URL
        $path = strtok($path, '?');
        $baseUrl = rtrim(BASE_URL, '/');
        $pathInfo = parse_url($path);
        $path = $pathInfo['path'] ?? $path;
        
        // Strip the base URL path if present
        $baseUrlPath = parse_url($baseUrl, PHP_URL_PATH) ?: '';
        if ($baseUrlPath && strpos($path, $baseUrlPath) === 0) {
            $path = substr($path, strlen($baseUrlPath));
        }
        $path = ltrim($path, '/');
    }
    
    if (empty($path)) {
        return [['label' => 'Dashboard', 'url' => url('index.php'), 'is_current' => true]];
    }
    
    // Split path into segments
    $segments = array_filter(explode('/', $path), function($seg) {
        return $seg !== '' && $seg !== 'index.php';
    });
    
    if (empty($segments)) {
        return [['label' => 'Dashboard', 'url' => url('index.php'), 'is_current' => true]];
    }
    
    $breadcrumbs = [];
    $buildingUrl = '';
    
    // Get list of valid parent modules
    $validModules = getModuleList();
    
    // Map common module paths to friendly labels
    $labelMap = [
        'modules' => 'Modules',
        'index.php' => 'Dashboard',
        'employees' => 'Employees',
        'departments' => 'Departments',
        'designations' => 'Designations',
        'attendance' => 'Attendance',
        'leave_requests' => 'Leave Requests',
        'holidays' => 'Holidays',
        'projects' => 'Projects',
        'tasks' => 'Tasks',
        'timesheets' => 'Timesheets',
        'clients' => 'Clients',
        'leads' => 'Leads',
        'deals' => 'Deals',
        'invoices' => 'Invoices',
        'payments' => 'Payments',
        'expenses' => 'Expenses',
        'expense_categories' => 'Expense Categories',
        'tax_settings' => 'Tax Settings',
        'assets' => 'Assets',
        'asset_categories' => 'Asset Categories',
        'asset_assignments' => 'Asset Assignments',
        'maintenance' => 'Maintenance',
        'licenses' => 'Software Licenses',
        'vendors' => 'Vendors',
        'inventory' => 'Inventory',
        'tickets' => 'Tickets',
        'ticket_categories' => 'Ticket Categories',
        'slas' => 'SLA Management',
        'knowledge_base' => 'Knowledge Base',
        'users' => 'Users',
        'roles' => 'Roles',
        'permissions' => 'Permissions',
        'audit_logs' => 'Audit Logs',
        'activity_logs' => 'Activity Logs',
        'analytics' => 'Analytics',
        'reports' => 'Reports',
        'scheduled_reports' => 'Scheduled Reports',
        'template_designer' => 'Template Designer',
        'executive_dashboard' => 'Executive Dashboard',
        'hrms_dashboard' => 'HRMS Dashboard',
        'crm_dashboard' => 'CRM Dashboard',
        'helpdesk_dashboard' => 'Helpdesk Dashboard',
        'assets_dashboard' => 'Assets Dashboard',
        'finance' => 'Finance',
        'create.php' => 'Create',
        'edit.php' => 'Edit',
        'view.php' => 'View',
        'delete.php' => 'Delete',
        'documents.php' => 'Documents',
        // The customer and contractor masters use one form.php for both create and
        // edit; without this they read "Customers › Form" in the trail.
        'form.php' => 'Record Details',
        'onboarding.php' => 'Onboarding',
        'people_management' => 'People Management',
        'print.php' => 'Print',
        'approve.php' => 'Approve',
        'cancel.php' => 'Cancel',
        'duplicate.php' => 'Duplicate',
        'send.php' => 'Send',
    ];
    
    // Special handling for finance module subdirectories
    $financeLabels = [
        'chart_of_accounts' => 'Chart of Accounts',
        'account_groups' => 'Account Groups',
        'account_types' => 'Account Types',
        'opening_balances' => 'Opening Balances',
        'settings' => 'Settings',
        'journal_entries' => 'Journal Entries',
        'voucher_types' => 'Voucher Types',
        'customer_invoices' => 'Customer Invoices',
        'customer_receipts' => 'Customer Receipts',
        'vendor_bills' => 'Vendor Bills',
        'vendor_payments' => 'Vendor Payments',
        'credit_notes' => 'Credit Notes',
        'debit_notes' => 'Debit Notes',
        'bank_accounts' => 'Bank Accounts',
        'bank_transactions' => 'Bank Transactions',
        'bank_reconciliation' => 'Bank Reconciliation',
        'cash_accounts' => 'Cash Accounts',
        'cash_transactions' => 'Cash Transactions',
        'budgets' => 'Budgets',
        'budget_versions' => 'Budget Versions',
        'budget_monitoring' => 'Budget Monitoring',
        'budget_reports' => 'Budget Reports',
        'budget_transfers' => 'Budget Transfers',
        'budget_revisions' => 'Budget Revisions',
        'company_expenses' => 'Company Expenses',
        'employee_claims' => 'Employee Claims',
        'expense_approvals' => 'Expense Approvals',
        'expense_reimbursements' => 'Expense Reimbursements',
        'aging_reports' => 'Aging Reports',
        'ar_dashboard' => 'AR Dashboard',
        'ap_aging' => 'AP Aging',
        'customer_statements' => 'Customer Statements',
        'financial_year' => 'Financial Years',
    ];
    
    $labelMap = array_merge($labelMap, $financeLabels);
    
    // Build breadcrumbs - track position relative to parent module
    $parentModuleFoundAt = -1;
    $currentParentModule = null;
    
    foreach ($segments as $index => $segment) {
        // Skip "modules" segment entirely
        if ($segment === 'modules') {
            continue;
        }
        
        $cleanSegment = basename($segment, '.php');
        
        // Check if this is a top-level parent module (has its own index.php directly under /modules)
        $isParentModule = in_array($cleanSegment, $validModules);
        
        if ($isParentModule && $parentModuleFoundAt === -1) {
            // This is the first parent module found - this becomes our anchor
            $parentModuleFoundAt = count($breadcrumbs);
            $currentParentModule = $cleanSegment;
            $buildingUrl = '/modules/' . $cleanSegment;
            
            // Get label for this module
            $label = str_replace(['_', '-'], ' ', $cleanSegment);
            $label = ucwords($label);
            
            if (isset($labelMap[$cleanSegment])) {
                $label = $labelMap[$cleanSegment];
            }
            
            // If this is also the last segment, it's the current page
            $isLast = ($index === count($segments) - 1);
            
            $breadcrumbs[] = [
                'label' => $label,
                'url' => $isLast ? '#' : url('modules/' . $cleanSegment . '/index.php'),
                'is_current' => $isLast,
            ];
            continue;
        }
        
        // For segments after the parent module (sub-modules, actions, etc.)
        if ($parentModuleFoundAt !== -1) {
            $isLast = ($index === count($segments) - 1);
            
            // Skip index.php in middle of path (already handled by parent module or sub-module)
            if ($segment === 'index.php') {
                // Update previous breadcrumb to not be current since we're on its index page
                if (!empty($breadcrumbs)) {
                    $breadcrumbs[count($breadcrumbs) - 1]['is_current'] = $isLast;
                    if (!$isLast) {
                        $breadcrumbs[count($breadcrumbs) - 1]['url'] = url(ltrim($buildingUrl, '/'));
                    }
                }
                continue;
            }
            
            // Check if this segment is a sub-module with its own index.php
            $isSubModule = ($currentParentModule && isSubModule($currentParentModule, $cleanSegment));
            
            if ($isSubModule) {
                // This is a sub-module like customer_invoices under finance
                $buildingUrl .= '/' . $cleanSegment;
                
                $label = str_replace(['_', '-'], ' ', $cleanSegment);
                $label = ucwords($label);
                
                if (isset($labelMap[$cleanSegment])) {
                    $label = $labelMap[$cleanSegment];
                } elseif (isset($labelMap[$segment])) {
                    $label = $labelMap[$segment];
                }
                
                $fullUrl = url(ltrim($buildingUrl, '/'));
                
                $breadcrumbs[] = [
                    'label' => $label,
                    'url' => $isLast ? '#' : $fullUrl . '/index.php',
                    'is_current' => $isLast,
                ];
            } else {
                // This is an action file (create.php, edit.php, etc.) or further nesting
                $buildingUrl .= '/' . $segment;
                
                // Convert filename to label
                $label = str_replace(['_', '-'], ' ', $cleanSegment);
                $label = ucwords($label);
                
                // Check if we have a mapped label
                if (isset($labelMap[$segment])) {
                    $label = $labelMap[$segment];
                } elseif (isset($labelMap[$cleanSegment])) {
                    $label = $labelMap[$cleanSegment];
                }
                
                $fullUrl = url(ltrim($buildingUrl, '/'));
                
                $breadcrumbs[] = [
                    'label' => $label,
                    'url' => $isLast ? '#' : $fullUrl,
                    'is_current' => $isLast,
                ];
            }
        }
    }
    
    // If no parent module found (edge case), fall back to original logic
    if ($parentModuleFoundAt === -1 && !empty($segments)) {
        $buildingUrl = '';
        foreach ($segments as $index => $segment) {
            if ($segment === 'modules') {
                continue;
            }
            
            $buildingUrl .= '/' . $segment;
            $isLast = ($index === count($segments) - 1);
            
            $label = str_replace(['_', '-'], ' ', basename($segment, '.php'));
            $label = ucwords($label);
            
            if (isset($labelMap[$segment])) {
                $label = $labelMap[$segment];
            } elseif (isset($labelMap[basename($segment, '.php')])) {
                $label = $labelMap[basename($segment, '.php')];
            }
            
            if ($segment === 'index.php' && $index < count($segments) - 1) {
                continue;
            }
            
            $fullUrl = url(ltrim($buildingUrl, '/'));
            
            $breadcrumbs[] = [
                'label' => $label,
                'url' => $isLast ? '#' : $fullUrl,
                'is_current' => $isLast,
            ];
        }
    }
    
    // If empty, add dashboard
    if (empty($breadcrumbs)) {
        $breadcrumbs[] = ['label' => 'Dashboard', 'url' => url('index.php'), 'is_current' => true];
    }
    
    return $breadcrumbs;
}

/**
 * Render breadcrumb navigation HTML
 * 
 * @param array|null $items Optional pre-built breadcrumb items
 * @return string HTML markup for breadcrumbs
 */
function renderBreadcrumbs($items = null)
{
    if ($items === null) {
        $items = generateBreadcrumbs();
    }
    
    if (empty($items)) {
        return '';
    }
    
    $html = '<nav class="breadcrumb" aria-label="Breadcrumb">';
    $html .= '<ol class="flex items-center space-x-2 text-sm text-gray-600">';
    
    foreach ($items as $index => $item) {
        $isLast = $item['is_current'] ?? ($index === count($items) - 1);
        $label = htmlspecialchars($item['label']);
        
        if ($isLast) {
            // Current page - not a link
            $html .= '<li class="flex items-center">';
            $html .= '<i class="fas fa-chevron-right text-xs mx-2 text-gray-400"></i>';
            $html .= '<span class="font-semibold text-gray-800">' . $label . '</span>';
            $html .= '</li>';
        } else {
            // Link to parent pages
            $url = htmlspecialchars($item['url']);
            $html .= '<li class="flex items-center">';
            if ($index > 0) {
                $html .= '<i class="fas fa-chevron-right text-xs mx-2 text-gray-400"></i>';
            }
            $html .= '<a href="' . $url . '" class="hover:text-blue-600 transition-colors">';
            $html .= $label;
            $html .= '</a>';
            $html .= '</li>';
        }
    }
    
    $html .= '</ol>';
    $html .= '</nav>';
    
    return $html;
}
