<?php
/**
 * Shared definition of the engagement (contract) field set.
 *
 * The same terms are entered in two places: on their own in contract_form.php,
 * and inline on the contractor master form so registering a person and opening
 * their first engagement is one action rather than two screens. Defining the
 * fields twice would let the two copies drift, so both pages call these.
 */

/** Option lists for the engagement fields, loaded once per request. */
function contractorEngagementOptions($db)
{
    $vendorOpts = [];
    foreach ($db->query("SELECT id, vendor_code, vendor_name FROM vendors WHERE status='active' ORDER BY vendor_name") as $v) {
        $vendorOpts[$v['id']] = ($v['vendor_code'] ? $v['vendor_code'] . ' — ' : '') . $v['vendor_name'];
    }

    $empOpts = [];
    foreach ($db->query("SELECT id, first_name, last_name FROM employees WHERE status='active' ORDER BY first_name") as $e) {
        $empOpts[$e['id']] = trim($e['first_name'] . ' ' . $e['last_name']);
    }

    return [
        'type_labels'   => lookupOptions('contractor.contract_type', [
            'statement_of_work' => 'Statement of Work (SOW)', 'purchase_order' => 'Purchase Order',
            'direct_engagement' => 'Direct Engagement', 'retainer' => 'Retainer',
            'framework_agreement' => 'Framework Agreement', 'master_service_agreement' => 'Master Service Agreement',
            'staffing_placement' => 'Agency Staffing Placement',
        ]),
        'arrangements'  => lookupOptions('contractor.work_arrangement', ['on_site' => 'On-site', 'off_site' => 'Off-site', 'hybrid' => 'Hybrid', 'remote' => 'Remote']),
        'billing'       => lookupOptions('contractor.billing_frequency', ['weekly' => 'Weekly', 'bi_weekly' => 'Every two weeks', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'upon_milestone' => 'Upon milestone', 'upon_delivery' => 'Upon delivery']),
        'rate_bases'    => contractorRateBases(),
        'payment_terms' => lookupOptions('trade.payment_terms', []),
        'vendors'       => $vendorOpts,
        'departments'   => array_column($db->query("SELECT id, department_name FROM departments WHERE status='active' ORDER BY department_name")->fetchAll(), 'department_name', 'id'),
        'designations'  => array_column($db->query("SELECT id, designation_name FROM designations WHERE status='active' ORDER BY designation_name")->fetchAll(), 'designation_name', 'id'),
        'employees'     => $empOpts,
        'projects'      => array_column($db->query("SELECT id, project_name FROM projects ORDER BY project_name LIMIT 400")->fetchAll(), 'project_name', 'id'),
        'purchase_orders' => array_column($db->query("SELECT id, po_number FROM purchase_orders ORDER BY id DESC LIMIT 300")->fetchAll(), 'po_number', 'id'),
        'currencies'    => array_column($db->query("SELECT code, name FROM currencies WHERE is_active = 1 ORDER BY sort_order")->fetchAll(), 'name', 'code'),
    ];
}

/**
 * Section descriptors for contractorRenderSection(). $withNumber shows the
 * contract number card, which only makes sense on the dedicated contract form;
 * on the master form the number is shown as a read-only caption instead.
 */
function contractorEngagementSections(array $opts, $withNumber = true)
{
    $sections = [];

    if ($withNumber) {
        $sections[] = ['title' => 'Contract', 'icon' => 'fa-hashtag', 'lockable' => true, 'fields' => [
            ['n' => 'contract_number', 'l' => 'Contract Number', 't' => 'static', 'span' => 1,
             'hint' => 'Allocated from the numbering scheme'],
        ]];
    }

    $sections[] = ['title' => 'Engagement', 'icon' => 'fa-file-signature', 'lockable' => true, 'fields' => [
        ['n' => 'title', 'l' => 'Contract Title', 't' => 'text', 'span' => 2, 'req' => true, 'ph' => 'e.g. Substation commissioning support'],
        ['n' => 'contract_type', 'l' => 'Contract Type', 't' => 'select', 'opts' => $opts['type_labels'], 'span' => 1],
        ['n' => 'supply_source', 'l' => 'Supply Source', 't' => 'select', 'span' => 1, 'blank' => '',
         'opts' => ['direct' => 'Direct (we engage the person)', 'agency' => 'Agency-supplied']],
        ['n' => 'agency_vendor_id', 'l' => 'Supplying Vendor / Agency', 't' => 'select', 'opts' => $opts['vendors'], 'span' => 2,
         'blank' => '— Direct, no agency —', 'hint' => 'Required when agency-supplied; invoices route through this vendor'],
        ['n' => 'purchase_order_id', 'l' => 'Linked Purchase Order', 't' => 'select', 'opts' => $opts['purchase_orders'], 'span' => 1, 'blank' => '— None —'],
        ['n' => 'po_reference', 'l' => 'External PO / Ref.', 't' => 'text', 'span' => 1, 'hint' => 'When the PO lives outside this ERP'],
    ]];

    $sections[] = ['title' => 'Scope of Work', 'icon' => 'fa-list-check', 'lockable' => true, 'fields' => [
        ['n' => 'scope_of_work', 'l' => 'Deliverables and scope', 't' => 'textarea', 'span' => 4, 'rows' => 4],
    ]];

    $sections[] = ['title' => 'Assignment & Term', 'icon' => 'fa-calendar-range', 'lockable' => true, 'fields' => [
        ['n' => 'start_date', 'l' => 'Start Date', 't' => 'date', 'span' => 1, 'req' => true],
        ['n' => 'end_date', 'l' => 'End Date', 't' => 'date', 'span' => 1, 'hint' => 'Blank = open-ended'],
        ['n' => 'department_id', 'l' => 'Department', 't' => 'select', 'opts' => $opts['departments'], 'span' => 1, 'blank' => '— Not set —'],
        ['n' => 'designation_id', 'l' => 'Designation', 't' => 'select', 'opts' => $opts['designations'], 'span' => 1, 'blank' => '— Not set —'],
        ['n' => 'contractual_job_title', 'l' => 'Contractual Job Title', 't' => 'text', 'span' => 2, 'hint' => 'As written on the contract, independent of the designation list'],
        ['n' => 'manager_employee_id', 'l' => 'Reporting Manager', 't' => 'select', 'opts' => $opts['employees'], 'span' => 1, 'blank' => '— Not set —'],
        ['n' => 'project_id', 'l' => 'Project', 't' => 'select', 'opts' => $opts['projects'], 'span' => 1, 'blank' => '— None —'],
        ['n' => 'work_arrangement', 'l' => 'Work Arrangement', 't' => 'select', 'opts' => $opts['arrangements'], 'span' => 1, 'blank' => '— Not set —'],
        ['n' => 'fte_allocation', 'l' => 'Allocation %', 't' => 'number', 'span' => 1, 'step' => '0.01', 'min' => '0', 'max' => '100'],
        ['n' => 'timesheet_required', 'l' => 'Timesheets required', 't' => 'checkbox', 'span' => 1, 'hint' => 'Engagement is billed against recorded hours'],
        ['n' => 'security_clearance', 'l' => 'Security Clearance', 't' => 'text', 'span' => 1],
        ['n' => 'badge_number', 'l' => 'Badge / Access Card', 't' => 'text', 'span' => 1],
    ]];

    $sections[] = ['title' => 'Commercial Terms', 'icon' => 'fa-coins', 'lockable' => true, 'fields' => [
        ['n' => 'rate_basis', 'l' => 'Rate Basis', 't' => 'select', 'opts' => $opts['rate_bases'], 'span' => 1, 'blank' => '— Not set —'],
        ['n' => 'rate_amount', 'l' => 'Rate', 't' => 'number', 'span' => 1, 'step' => '0.01', 'min' => '0'],
        ['n' => 'currency_code', 'l' => 'Currency', 't' => 'select', 'opts' => $opts['currencies'], 'span' => 1, 'blank' => '— Default —'],
        ['n' => 'estimated_value', 'l' => 'Estimated Total Value', 't' => 'number', 'span' => 1, 'step' => '0.01', 'min' => '0'],
        ['n' => 'not_to_exceed_amount', 'l' => 'Not-to-Exceed', 't' => 'number', 'span' => 1, 'step' => '0.01', 'min' => '0', 'hint' => 'Hard ceiling for SOW engagements'],
        ['n' => 'billing_frequency', 'l' => 'Billing Frequency', 't' => 'select', 'opts' => $opts['billing'], 'span' => 1, 'blank' => '— Not set —'],
        ['n' => 'payment_terms', 'l' => 'Payment Terms', 't' => 'select', 'opts' => $opts['payment_terms'], 'span' => 1, 'blank' => '— Not set —'],
    ]];

    $sections[] = ['title' => 'Extension Control', 'icon' => 'fa-rotate', 'lockable' => true, 'fields' => [
        ['n' => 'extension_limit', 'l' => 'Max Extensions', 't' => 'number', 'span' => 1, 'min' => '0', 'max' => '999', 'hint' => 'Blank = no agreed ceiling'],
        ['n' => 'extension_period_days', 'l' => 'Extension Period (days)', 't' => 'number', 'span' => 1, 'min' => '1', 'max' => '3650'],
    ]];

    return $sections;
}

/**
 * Did the user actually fill the inline engagement block? Empty blocks on the
 * master form must not create an empty draft contract.
 */
function contractorEngagementPosted(array $post)
{
    foreach (['title', 'contract_type', 'scope_of_work', 'start_date', 'end_date', 'rate_amount',
              'agency_vendor_id', 'purchase_order_id', 'department_id', 'project_id',
              'estimated_value', 'not_to_exceed_amount', 'contractual_job_title'] as $field) {
        if (trim((string) ($post[$field] ?? '')) !== '') {
            return true;
        }
    }
    return !empty($post['timesheet_required']);
}
