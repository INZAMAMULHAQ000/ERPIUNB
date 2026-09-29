<?php
/**
 * Shared Tailwind UI helpers for the Tax Management module.
 * Every tax page (and the invoice/bill/expense forms that embed a tax picker)
 * should use these helpers so the module renders as one consistent system.
 */

require_once __DIR__ . '/../TaxService.php';

/** Format an amount with the configured currency symbol (no conversion). */
function taxui_amount($amount) {
    $db = getDB();
    static $symbol = null;
    if ($symbol === null) {
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'finance_currency_symbol'");
        $stmt->execute();
        $symbol = $stmt->fetchColumn() ?: 'AED';
    }
    return number_format((float)$amount, TaxService::precision(), '.', ',') . ' ' . $symbol;
}

/** HTML <option> list for tax statuses. */
function taxui_status_options($selected = '') {
    $html = '';
    foreach (TaxService::statuses() as $val => $label) {
        $sel = ((string)$selected === (string)$val) ? ' selected' : '';
        $html .= '<option value="' . $val . '"' . $sel . '>' . $label . '</option>';
    }
    return $html;
}

/** Pill badge for a tax status. */
function taxui_status_badge($status) {
    $map = [
        'taxable'      => ['bg-emerald-100 text-emerald-800', 'Taxable'],
        'zero_rated'   => ['bg-sky-100 text-sky-800', 'Zero Rated'],
        'exempt'       => ['bg-amber-100 text-amber-800', 'Exempt'],
        'out_of_scope' => ['bg-slate-100 text-slate-700', 'Out of Scope'],
    ];
    $b = $map[$status] ?? ['bg-gray-100 text-gray-700', ucwords((string)$status)];
    return '<span class="px-2 py-1 text-xs rounded font-medium ' . $b[0] . '">' . $b[1] . '</span>';
}

/** HTML <option> list for tax directions. */
function taxui_direction_options($selected = '') {
    $map = [
        'output'     => 'Output Tax',
        'input'      => 'Input Tax',
        'adjustment' => 'Adjustment',
    ];
    $html = '';
    foreach ($map as $val => $label) {
        $sel = ((string)$selected === (string)$val) ? ' selected' : '';
        $html .= '<option value="' . $val . '"' . $sel . '>' . $label . '</option>';
    }
    return $html;
}

/** Pill badge for a tax direction. */
function taxui_direction_badge($direction) {
    $map = [
        'output'     => ['bg-indigo-100 text-indigo-800', 'Output Tax'],
        'input'      => ['bg-violet-100 text-violet-800', 'Input Tax'],
        'adjustment' => ['bg-rose-100 text-rose-800', 'Adjustment'],
    ];
    $b = $map[$direction] ?? ['bg-gray-100 text-gray-700', ucwords((string)$direction)];
    return '<span class="px-2 py-1 text-xs rounded font-medium ' . $b[0] . '">' . $b[1] . '</span>';
}

/** Pill badge for a tax period status. */
function taxui_period_badge($status) {
    $map = [
        'open'      => ['bg-emerald-100 text-emerald-800', 'Open'],
        'submitted' => ['bg-amber-100 text-amber-800', 'Submitted'],
        'closed'    => ['bg-rose-100 text-rose-800', 'Closed'],
    ];
    $b = $map[$status] ?? ['bg-gray-100 text-gray-700', ucwords((string)$status)];
    return '<span class="px-2 py-1 text-xs rounded font-medium ' . $b[0] . '">' . $b[1] . '</span>';
}

/** Pill badge for a transaction row status. */
function taxui_txn_badge($status) {
    $map = [
        'active'   => ['bg-emerald-100 text-emerald-800', 'Active'],
        'reversed' => ['bg-rose-100 text-rose-800', 'Reversed'],
    ];
    $b = $map[$status] ?? ['bg-gray-100 text-gray-700', ucwords((string)$status)];
    return '<span class="px-2 py-1 text-xs rounded font-medium ' . $b[0] . '">' . $b[1] . '</span>';
}

/** <select> of tax types for filters/forms. */
function taxui_types_select($selected = '', $name = 'tax_type_id', $includeEmpty = true) {
    $db = getDB();
    $types = $db->query("SELECT id, name, code, status FROM tax_types ORDER BY name")->fetchAll();
    $html = '<select name="' . $name . '" class="w-full px-3 py-2 border rounded text-sm bg-white focus:ring-2 focus:ring-blue-500">';
    if ($includeEmpty) $html .= '<option value="">All Tax Types</option>';
    foreach ($types as $t) {
        $label = $t['name'] . ($t['status'] !== 'active' ? ' (inactive)' : '');
        $sel = ((string)$selected === (string)$t['id']) ? ' selected' : '';
        $html .= '<option value="' . $t['id'] . '"' . $sel . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    $html .= '</select>';
    return $html;
}

/**
 * <select> of tax rates for a given tax type (used by invoice/bill/expense
 * line forms). Options carry data-rate + data-default for client-side math.
 */
function taxui_rates_for_type($taxTypeId, $selected = null, $name = 'tax_rate_id', $includeNoTax = true, $includeInactive = false) {
    if (!$taxTypeId) {
        return '<select name="' . $name . '" class="w-full px-3 py-2 border rounded text-sm bg-white focus:ring-2 focus:ring-blue-500"><option value="">—</option></select>';
    }
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM tax_rates WHERE tax_type_id = ?" . ($includeInactive ? '' : " AND status = 'active'") . " ORDER BY rate_percentage DESC, id");
    $stmt->execute([$taxTypeId]);
    $rates = $stmt->fetchAll();
    $html = '<select name="' . $name . '" class="w-full px-3 py-2 border rounded text-sm bg-white focus:ring-2 focus:ring-blue-500 tax-rate-select">';
    if ($includeNoTax) $html .= '<option value="" data-rate="0" data-name="No Tax">No Tax</option>';
    foreach ($rates as $r) {
        $pct = (float)$r['rate_percentage'];
        $sel = ($selected !== null && (string)$selected === (string)$r['id']) ? ' selected' : '';
        $def = (int)$r['is_default'] ? ' data-default="1"' : '';
        $nameTxt = $r['rate_name'] . ' (' . rtrim(rtrim(number_format($pct, 4), '0'), '.') . '%)';
        $html .= '<option value="' . $r['id'] . '"' . $sel . $def . ' data-rate="' . $pct . '" data-name="' . htmlspecialchars($r['rate_name'] . (rtrim(rtrim(number_format($pct, 4), '0'), '.') . '%'), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($nameTxt, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    $html .= '</select>';
    return $html;
}

/** <select> of all tax rates grouped by type (for filters). */
function taxui_rates_all($selected = '', $name = 'tax_rate_id') {
    $types = TaxService::listTaxTypes(true);
    $html = '<select name="' . $name . '" class="w-full px-3 py-2 border rounded text-sm bg-white focus:ring-2 focus:ring-blue-500">';
    $html .= '<option value="">All Rates</option>';
    foreach ($types as $t) {
        $html .= '<optgroup label="' . htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8') . '">';
        foreach (TaxService::listTaxRates((int)$t['id'], true) as $r) {
            $labels = $r['rate_name'] . ' (' . rtrim(rtrim(number_format((float)$r['rate_percentage'], 4), '0'), '.') . '%)';
            $sel = ((string)$selected === (string)$r['id']) ? ' selected' : '';
            $html .= '<option value="' . $r['id'] . '"' . $sel . '>' . htmlspecialchars($labels, ENT_QUOTES, 'UTF-8') . '</option>';
        }
        $html .= '</optgroup>';
    }
    $html .= '</select>';
    return $html;
}

/** <select> of tax groups. */
function taxui_groups_select($selected = '', $name = 'tax_group_id', $includeEmpty = true) {
    $groups = TaxService::listTaxGroups(true);
    $html = '<select name="' . $name . '" class="w-full px-3 py-2 border rounded text-sm bg-white focus:ring-2 focus:ring-blue-500 tax-group-select">';
    if ($includeEmpty) $html .= '<option value="">No Group</option>';
    foreach ($groups as $g) {
        $sel = ((string)$selected === (string)$g['id']) ? ' selected' : '';
        $html .= '<option value="' . $g['id'] . '"' . $sel . '>' . htmlspecialchars($g['group_name'], ENT_QUOTES, 'UTF-8') . '</option>';
    }
    $html .= '</select>';
    return $html;
}

/** <select> of active exemptions. */
function taxui_exemptions_select($selected = '', $name = 'tax_exemption_id') {
    $db = getDB();
    $rows = $db->query("SELECT id, exemption_code, name FROM tax_exemptions WHERE status = 'active' ORDER BY name")->fetchAll();
    $html = '<select name="' . $name . '" class="w-full px-3 py-2 border rounded text-sm bg-white focus:ring-2 focus:ring-blue-500 tax-exemption-select">';
    $html .= '<option value="">None</option>';
    foreach ($rows as $r) {
        $sel = ((string)$selected === (string)$r['id']) ? ' selected' : '';
        $html .= '<option value="' . $r['id'] . '"' . $sel . '>' . htmlspecialchars($r['name'] . ' (' . $r['exemption_code'] . ')', ENT_QUOTES, 'UTF-8') . '</option>';
    }
    $html .= '</select>';
    return $html;
}

/**
 * Per-line tax picker for document forms (invoice/bill/expense lines).
 * Renders a rate <select>, a tax-status <select> and (hidden unless the
 * status is exempt) an exemption <select>. Each rate option carries
 * data-rate + data-type so client-side math stays in sync with the engine.
 *
 * @param string $namePrefix  e.g. "items[0]"
 * @param int    $rateId      selected tax_rate_id
 * @param int    $typeId      selected tax_type_id (used to re-group the select)
 * @param string $status      selected tax_status
 * @param int    $exemptionId selected exemption id
 */
function taxui_doc_line_picker($namePrefix, $rateId = null, $typeId = null, $status = 'taxable', $exemptionId = null) {
    $types = TaxService::listTaxTypes();
    $db = getDB();
    $exemptions = $db->query("SELECT id, exemption_code, name FROM tax_exemptions WHERE status = 'active' ORDER BY name")->fetchAll();

    // Which type drives the rate list? Prefer the selected type, else the default.
    $activeTypeId = $typeId ?: TaxService::getDefaultTaxType()['id'] ?? 0;

    $html = '<div class="tax-doc-picker flex flex-col gap-1">';

    // --- Rate select ---
    $html .= '<select name="' . $namePrefix . '[tax_rate_id]" class="w-full px-2 py-2 border rounded text-center tax-rate-select tax-doc-rate">';
    // No Tax option uses the first zero-rated active rate when available so the
    // engine sees a pinned 0% rate (keeps the line "taxable" with zero tax).
    $zeroRate = null;
    foreach ($types as $t) {
        foreach (TaxService::listTaxRates((int)$t['id']) as $r) {
            if ((float)$r['rate_percentage'] == 0 && !$zeroRate) $zeroRate = $r;
        }
    }
    if ($zeroRate) {
        $sel = ($rateId !== null && (string)$rateId === (string)$zeroRate['id']) ? ' selected' : '';
        $html .= '<option value="' . $zeroRate['id'] . '"' . $sel . ' data-rate="0" data-type="' . (int)$zeroRate['tax_type_id'] . '">No Tax</option>';
    }
    foreach ($types as $t) {
        $rates = TaxService::listTaxRates((int)$t['id']);
        if (!$rates) continue;
        $html .= '<optgroup label="' . htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8') . '">';
        foreach ($rates as $r) {
            $pct = (float)$r['rate_percentage'];
            $label = $r['rate_name'] . ' (' . rtrim(rtrim(number_format($pct, 4), '0'), '.') . '%)';
            $sel = ($rateId !== null && (string)$rateId === (string)$r['id']) ? ' selected' : '';
            $html .= '<option value="' . $r['id'] . '"' . $sel . ' data-rate="' . $pct . '" data-type="' . (int)$r['tax_type_id'] . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
        }
        $html .= '</optgroup>';
    }
    $html .= '</select>';

    // --- Tax status select ---
    $html .= '<select name="' . $namePrefix . '[tax_status]" class="w-full px-2 py-1 border rounded text-xs bg-white tax-status-select tax-doc-status">';
    foreach (TaxService::statuses() as $val => $label) {
        $sel = ((string)$status === (string)$val) ? ' selected' : '';
        $html .= '<option value="' . $val . '"' . $sel . '>' . $label . '</option>';
    }
    $html .= '</select>';

    // --- Exemption select (visible only when status = exempt) ---
    $hidden = TaxService::isExempt($status) ? '' : ' hidden';
    $html .= '<select name="' . $namePrefix . '[exemption_id]" class="w-full px-2 py-1 border rounded text-xs bg-white text-amber-700 tax-exemption-select tax-doc-exemption"' . $hidden . '>';
    $html .= '<option value="">— Exemption —</option>';
    foreach ($exemptions as $e) {
        $sel = ($exemptionId !== null && (string)$exemptionId === (string)$e['id']) ? ' selected' : '';
        $html .= '<option value="' . $e['id'] . '"' . $sel . '>' . htmlspecialchars($e['name'] . ' (' . $e['exemption_code'] . ')', ENT_QUOTES, 'UTF-8') . '</option>';
    }
    $html .= '</select>';

    $html .= '</div>';
    return $html;
}

/** Human label for a source module. */
function taxui_source_label($module) {
    $map = [
        'customer_invoice' => 'Customer Invoice',
        'vendor_bill'      => 'Vendor Bill',
        'company_expense'  => 'Expense',
        'credit_note'      => 'Credit Note',
        'debit_note'       => 'Debit Note',
        'tax_adjustment'   => 'Tax Adjustment',
    ];
    return $map[$module] ?? ucwords(str_replace('_', ' ', (string)$module));
}

/** Sub-module navigation tabs for the Tax area. */
function taxui_tabs($active = 'dashboard') {
    $tabs = [
        'dashboard'    => ['Tax Dashboard',    'modules/finance/tax/dashboard/index.php',    'tax.view'],
        'types'        => ['Tax Types',        'modules/finance/tax/tax_types/index.php',    'tax.view'],
        'rates'        => ['Tax Rates',        'modules/finance/tax/tax_rates/index.php',    'tax.view'],
        'groups'       => ['Tax Groups',       'modules/finance/tax/tax_groups/index.php',    'tax.view'],
        'exemptions'   => ['Exemptions',       'modules/finance/tax/exemptions/index.php',   'tax.view'],
        'registrations'=> ['Registrations',    'modules/finance/tax/registrations/index.php','tax.view'],
        'periods'      => ['Periods',          'modules/finance/tax/periods/index.php',      'tax.period.manage'],
        'transactions' => ['Transactions',     'modules/finance/tax/transactions/index.php', 'tax.view'],
        'adjustments'  => ['Adjustments',      'modules/finance/tax/adjustments/index.php',  'tax.adjust'],
        'reports'      => ['Reports',          'modules/finance/tax/reports/index.php',      'tax.report'],
        'settings'     => ['Settings',         'modules/finance/tax/settings/index.php',     'tax.configure'],
    ];
    $html = '<div class="flex flex-wrap gap-2 mb-6">';
    foreach ($tabs as $key => list($label, $route, $perm)) {
        if (!hasPermission($perm)) continue;
        $isActive = $key === $active;
        $cls = $isActive
            ? 'bg-blue-600 text-white'
            : 'bg-white text-gray-700 hover:bg-gray-100 border border-slate-200';
        $html .= '<a href="' . url($route) . '" class="px-4 py-2 text-sm font-medium rounded-lg transition ' . $cls . '">' . $label . '</a>';
    }
    $html .= '</div>';
    return $html;
}

