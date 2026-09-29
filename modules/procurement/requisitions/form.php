<?php

/** P1 — PR Create / Edit (shared form; server-side calc only) */
$pageTitle = 'Purchase Requisition';

require_once __DIR__ . '/../bootstrap.php';

if (!hasPermission('procurement.view')) {
    die('Access Denied');
}

$db = getDB();

$editId = (int)($_GET['id'] ?? 0);

$pr = $editId
    ? ProcurementService::getPR($db, $editId)
    : null;

if ($editId && !$pr) {
    flash('error', 'Not found.');
    to('modules/procurement/requisitions/index.php');
}

if ($pr) {
    CompanyService::requireCompanyAccess(
        $db,
        $_SESSION['user_id'],
        (int)$pr['company_id']
    );
}

if ($pr && !ProcurementService::canEdit($pr)) {
    flash(
        'error',
        'This requisition is locked (' . $pr['status'] . ').'
    );

    to(
        'modules/procurement/requisitions/view.php?id=' . $editId
    );
}

if ($editId && !hasPermission('procurement.pr.edit')) {
    die('Access Denied');
}

if (!$editId && !hasPermission('procurement.pr.create')) {
    die('Access Denied');
}


/*
|--------------------------------------------------------------------------
| POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid request.');
        to('modules/procurement/requisitions/index.php');
    }


    /*
    |--------------------------------------------------------------------------
    | Normalize lines
    |--------------------------------------------------------------------------
    */

    $lines = [];

    foreach (($_POST['lines'] ?? []) as $l) {

        if (
            trim((string)($l['item_name'] ?? '')) === ''
            &&
            (float)($l['quantity'] ?? 0) == 0
        ) {
            continue;
        }

        $lines[] = [
            'item_type' =>
            $l['item_type'] ?? 'non_stock',

            'item_name' =>
            $l['item_name'] ?? '',

            'description' =>
            $l['description'] ?? '',

            'quantity' =>
            $l['quantity'] ?? 0,

            'unit' =>
            $l['unit'] ?? 'pcs',

            'est_unit_price' =>
            $l['est_unit_price'] ?? 0,

            'discount_amount' =>
            $l['discount_amount'] ?? 0,

            'tax_percent' =>
            $l['tax_percent'] ?? 0,

            'required_date' =>
            $l['required_date'] ?? null,

            'preferred_vendor_id' =>
            $l['preferred_vendor_id'] ?? null,

            'department_id' =>
            $l['department_id'] ?? null,

            'project_id' =>
            $l['project_id'] ?? null,

            'cost_center_id' =>
            $l['cost_center_id'] ?? null,

            'notes' =>
            $l['notes'] ?? '',
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Header
    |--------------------------------------------------------------------------
    */

    $header = [

        'company_id' =>
        $_POST['company_id'] ?? 0,

        'department_id' =>
        $_POST['department_id'] ?? 0,

        'project_id' =>
        $_POST['project_id'] ?? 0,

        'cost_center_id' =>
        $_POST['cost_center_id'] ?? 0,

        'branch' =>
        $_POST['branch'] ?? '',

        'requester_id' =>
        !empty($_POST['requester_id'])
            ? (int)$_POST['requester_id']
            : $_SESSION['user_id'],

        'request_date' =>
        $_POST['request_date'] ?? '',

        'required_date' =>
        $_POST['required_date'] ?? '',

        'priority' =>
        $_POST['priority'] ?? 'normal',

        'purpose' =>
        $_POST['purpose'] ?? '',

        'description' =>
        $_POST['description'] ?? '',

        'currency' =>
        $_POST['currency'] ?? 'AED',
    ];


    /*
    |--------------------------------------------------------------------------
    | Create / Update
    |--------------------------------------------------------------------------
    */

    if ($editId) {

        $res = ProcurementService::updatePR(
            $db,
            $editId,
            $header,
            $lines,
            $_SESSION['user_id']
        );
    } else {

        $res = ProcurementService::createPR(
            $db,
            $header,
            $lines,
            $_SESSION['user_id']
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */

    if (!empty($res['success'])) {

        flash(
            'success',
            'Requisition ' .
                ($res['number'] ?? 'saved') .
                ' — all totals computed server-side.'
        );

        if (isset($_POST['submit_after'])) {

            $pid = $editId
                ?: (int)$res['id'];

            $sr = ProcurementService::submit(
                $db,
                $pid,
                $_SESSION['user_id']
            );

            flash(
                $sr['success'] ? 'success' : 'error',
                $sr['success']
                    ? 'Submitted for approval.'
                    : $sr['message']
            );

            to(
                'modules/procurement/requisitions/view.php?id=' .
                    $pid
            );
        }

        to(
            'modules/procurement/requisitions/view.php?id=' .
                ($editId ?: (int)$res['id'])
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Service errors
    |--------------------------------------------------------------------------
    */

    foreach ($res['errors'] ?? [] as $e) {
        flash('error', $e);
    }
}


/*
|--------------------------------------------------------------------------
| Data
|--------------------------------------------------------------------------
*/

$companies = CompanyService::accessibleCompanies(
    $db,
    $_SESSION['user_id']
);

$departments = $db->query("
    SELECT id, department_name
    FROM departments
    WHERE status='active'
    ORDER BY department_name
")->fetchAll();

$projects = $db->query("
    SELECT id, project_name
    FROM projects
    WHERE status='active'
    ORDER BY project_name
")->fetchAll();

$cCs = $db->query("
    SELECT id, cost_center_name
    FROM cost_centers
    WHERE status='active'
    ORDER BY cost_center_name
")->fetchAll();

$users = $db->query("
    SELECT id, name
    FROM users
    WHERE status='active'
    ORDER BY name
")->fetchAll();

$vendors = $db->query("
    SELECT id, vendor_name
    FROM vendors
    WHERE status='active'
    ORDER BY vendor_name
")->fetchAll();

$items = $editId
    ? ProcurementService::getItems($db, $editId)
    : [];

$settings = ProcurementService::getSettings($db);

?>

<style>
    /*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

    #purchaseRequisitionForm label.error {
        display: block !important;
        width: 100% !important;
        margin: 4px 0 0 0 !important;
        padding: 0 !important;
        clear: both !important;
        color: #dc2626 !important;
        font-size: 12px !important;
        font-weight: 400 !important;
        line-height: 16px !important;
        position: static !important;
    }

    #purchaseRequisitionForm input.error,
    #purchaseRequisitionForm select.error,
    #purchaseRequisitionForm textarea.error {
        border-color: #ef4444 !important;
        box-shadow: none !important;
    }

    #purchaseRequisitionForm input.valid,
    #purchaseRequisitionForm select.valid,
    #purchaseRequisitionForm textarea.valid {
        border-color: #22c55e !important;
        box-shadow: none !important;
    }

    /*
|--------------------------------------------------------------------------
| Dynamic item validation
|--------------------------------------------------------------------------
*/

    #linesTable input.error,
    #linesTable select.error {
        border-color: #ef4444 !important;
    }

    #linesTable input.valid,
    #linesTable select.valid {
        border-color: #22c55e !important;
    }

    #linesTable label.error {
        display: block !important;
        color: #dc2626 !important;
        font-size: 11px !important;
        line-height: 15px !important;
        margin-top: 3px !important;
    }
</style>


<!--
|--------------------------------------------------------------------------
| Page Header
|--------------------------------------------------------------------------
-->

<div class="mb-6 flex justify-between items-center">

    <div>

        <h1 class="text-2xl font-bold text-gray-800">
            <?= $editId ? 'Edit' : 'New' ?>
            Purchase Requisition
        </h1>

        <?php if ($pr): ?>

            <p class="font-mono text-gray-500 text-sm">
                <?= sanitize($pr['pr_number']) ?>
            </p>

        <?php endif; ?>

    </div>

    <a
        href="<?= url('modules/procurement/requisitions/index.php') ?>"
        class="bg-white border px-3 py-2 rounded hover:bg-gray-50 text-sm">
        Back
    </a>

</div>


<!--
|--------------------------------------------------------------------------
| Main Form
|--------------------------------------------------------------------------
-->

<form
    method="post"
    id="purchaseRequisitionForm"
    class="space-y-5"
    novalidate>

    <input
        type="hidden"
        name="csrf_token"
        value="<?= csrfToken() ?>">


    <!--
    |--------------------------------------------------------------------------
    | Header Details
    |--------------------------------------------------------------------------
    -->

    <div class="bg-white rounded-lg shadow p-5 grid grid-cols-1 md:grid-cols-4 gap-4">


        <!-- Requester -->

        <div>

            <label
                for="requester_id"
                class="text-xs text-gray-500 block mb-1">
                Requester
                <span class="text-red-600">*</span>
            </label>

            <select
                name="requester_id"
                id="requester_id"
                class="w-full border rounded px-3 py-2 text-sm">

                <option value="">
                    — select —
                </option>

                <?php foreach ($users as $u): ?>

                    <option
                        value="<?= $u['id'] ?>"
                        <?= (
                            ($pr['requester_id'] ?? $_SESSION['user_id'])
                            == $u['id']
                        ) ? 'selected' : '' ?>>
                        <?= sanitize($u['name']) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- Company -->

        <div>

            <label
                for="company_id"
                class="text-xs text-gray-500 block mb-1">
                Company
                <span class="text-red-600">*</span>
            </label>

            <select
                name="company_id"
                id="company_id"
                class="w-full border rounded px-3 py-2 text-sm">

                <option value="">
                    — select —
                </option>

                <?php foreach ($companies as $c): ?>

                    <option
                        value="<?= $c['id'] ?>"
                        <?= (
                            ($pr['company_id'] ?? '')
                            == $c['id']
                        ) ? 'selected' : '' ?>>
                        <?= sanitize($c['company_name']) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- Department -->

        <div>

            <label
                for="department_id"
                class="text-xs text-gray-500 block mb-1">
                Department
                <span class="text-red-600">*</span>
            </label>

            <select
                name="department_id"
                id="department_id"
                class="w-full border rounded px-3 py-2 text-sm">

                <option value="">
                    — select —
                </option>

                <?php foreach ($departments as $d): ?>

                    <option
                        value="<?= $d['id'] ?>"
                        <?= (
                            ($pr['department_id'] ?? '')
                            == $d['id']
                        ) ? 'selected' : '' ?>>
                        <?= sanitize($d['department_name']) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- Priority -->

        <div>

            <label
                for="priority"
                class="text-xs text-gray-500 block mb-1">
                Priority
            </label>

            <select
                name="priority"
                id="priority"
                class="w-full border rounded px-3 py-2 text-sm">

                <?php foreach (
                    ['low', 'normal', 'high', 'urgent']
                    as $p
                ): ?>

                    <option
                        value="<?= $p ?>"
                        <?= (
                            ($pr['priority'] ?? 'normal') === $p
                        ) ? 'selected' : '' ?>>
                        <?= $p ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- Project -->

        <div>

            <label
                for="project_id"
                class="text-xs text-gray-500 block mb-1">
                Project
            </label>

            <select
                name="project_id"
                id="project_id"
                class="w-full border rounded px-3 py-2 text-sm">

                <option value="">
                    — none —
                </option>

                <?php foreach ($projects as $p): ?>

                    <option
                        value="<?= $p['id'] ?>"
                        <?= (
                            ($pr['project_id'] ?? '')
                            == $p['id']
                        ) ? 'selected' : '' ?>>
                        <?= sanitize($p['project_name']) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- Cost Center -->

        <div>

            <label
                for="cost_center_id"
                class="text-xs text-gray-500 block mb-1">
                Cost Center
            </label>

            <select
                name="cost_center_id"
                id="cost_center_id"
                class="w-full border rounded px-3 py-2 text-sm">

                <option value="">
                    — none —
                </option>

                <?php foreach ($cCs as $cc): ?>

                    <option
                        value="<?= $cc['id'] ?>"
                        <?= (
                            ($pr['cost_center_id'] ?? '')
                            == $cc['id']
                        ) ? 'selected' : '' ?>>
                        <?= sanitize($cc['cost_center_name']) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- Branch -->

        <div>

            <label
                for="branch"
                class="text-xs text-gray-500 block mb-1">
                Branch
            </label>

            <input
                type="text"
                name="branch"
                id="branch"
                value="<?= sanitize($pr['branch'] ?? '') ?>"
                maxlength="255"
                class="w-full border rounded px-3 py-2 text-sm">

        </div>


        <!-- Currency -->

        <div>

            <label
                for="currency"
                class="text-xs text-gray-500 block mb-1">
                Currency
            </label>

            <select
                name="currency"
                id="currency"
                class="w-full border rounded px-3 py-2 text-sm">

                <?php foreach (
                    ['AED', 'USD', 'EUR', 'GBP', 'SAR']
                    as $cu
                ): ?>

                    <option
                        value="<?= $cu ?>"
                        <?= (
                            ($pr['currency'] ?? $settings['default_currency'])
                            === $cu
                        ) ? 'selected' : '' ?>>
                        <?= $cu ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- Request Date -->

        <div>

            <label
                for="request_date"
                class="text-xs text-gray-500 block mb-1">
                Request Date
                <span class="text-red-600">*</span>
            </label>

            <input
                type="date"
                name="request_date"
                id="request_date"
                value="<?= sanitize(
                            $pr['request_date'] ?? date('Y-m-d')
                        ) ?>"
                class="w-full border rounded px-3 py-2 text-sm">

        </div>


        <!-- Required Date -->

        <div>

            <label
                for="required_date"
                class="text-xs text-gray-500 block mb-1">
                Required Date
                <span class="text-red-600">*</span>
            </label>

            <input
                type="date"
                name="required_date"
                id="required_date"
                value="<?= sanitize(
                            $pr['required_date'] ?? ''
                        ) ?>"
                class="w-full border rounded px-3 py-2 text-sm">

        </div>


        <!-- Purpose -->

        <div class="md:col-span-2">

            <label
                for="purpose"
                class="text-xs text-gray-500 block mb-1">
                Purpose
            </label>

            <input
                type="text"
                name="purpose"
                id="purpose"
                value="<?= sanitize($pr['purpose'] ?? '') ?>"
                maxlength="500"
                placeholder="e.g. Office upgrade"
                class="w-full border rounded px-3 py-2 text-sm">

        </div>


        <!-- Description -->

        <div class="md:col-span-4">

            <label
                for="description"
                class="text-xs text-gray-500 block mb-1">
                Description
            </label>

            <textarea
                name="description"
                id="description"
                rows="2"
                maxlength="2000"
                class="w-full border rounded px-3 py-2 text-sm"><?= sanitize($pr['description'] ?? '') ?></textarea>

        </div>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | Items
    |--------------------------------------------------------------------------
    -->

    <div class="bg-white rounded-lg shadow p-5">

        <div class="flex justify-between items-center mb-3">

            <h3 class="font-semibold text-gray-800">
                Items
            </h3>

            <!-- <button
                type="button"
                onclick="addLine()"
                class="text-blue-600 hover:underline text-sm">
                <i class="fas fa-plus mr-1"></i>
                Add Item
            </button> -->
            <button
                type="button"
                onclick="addItemWithValidation()"
                class="text-blue-600 hover:underline text-sm">
                <i class="fas fa-plus mr-1"></i>
                Add Item
            </button>

        </div>


        <table
            class="w-full text-sm"
            id="linesTable">

            <thead class="bg-gray-50 text-xs uppercase text-gray-500">

                <tr>

                    <th class="px-2 py-2 text-left w-40">
                        Type
                    </th>

                    <th class="px-2 py-2 text-left w-45">
                        Item *
                    </th>

                    <th class="px-2 py-2 text-right w-20">
                        Qty *
                    </th>

                    <th class="px-2 py-2 text-left w-16">
                        Unit *
                    </th>

                    <th class="px-2 py-2 text-right w-28">
                        Unit Price *
                    </th>

                    <th class="px-2 py-2 text-right w-24">
                        Tax %
                    </th>

                    <th class="px-2 py-2 text-right w-28">
                        Line Total (server)
                    </th>

                    <th></th>

                </tr>

            </thead>

            <tbody></tbody>

        </table>


        <input
            type="hidden"
            name="browser_total"
            id="browserTotal"
            value="">


        <div class="text-right mt-3 space-y-1 text-sm">

            <div>
                Subtotal:
                <b
                    class="font-mono"
                    id="tSub">
                    0.00
                </b>
            </div>

            <div>
                Tax + Discount shown per line · Grand total is finalized server-side.
            </div>

        </div>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | Buttons
    |--------------------------------------------------------------------------
    -->

    <div class="flex gap-2">

        <button
            type="submit"
            name="save_draft"
            value="1"
            class="bg-blue-600 text-white px-6 py-2.5 rounded hover:bg-blue-700 text-sm">
            <?= $editId ? 'Save Changes' : 'Save Draft' ?>
        </button>


        <?php if (
            !$editId ||
            $pr['status'] === 'rejected'
        ): ?>

            <button
                type="submit"
                name="submit_after"
                value="1"
                class="bg-emerald-600 text-white px-6 py-2.5 rounded hover:bg-emerald-700 text-sm">
                Save &amp; Submit for Approval
            </button>

        <?php endif; ?>

    </div>

</form>


<!--
|--------------------------------------------------------------------------
| JavaScript
|--------------------------------------------------------------------------
-->

<script>
    const vendors = <?= json_encode(
                        array_map(
                            fn($v) => [
                                'id' => $v['id'],
                                'n'  => $v['vendor_name']
                            ],
                            $vendors
                        ),
                        JSON_HEX_TAG |
                            JSON_HEX_AMP |
                            JSON_HEX_APOS |
                            JSON_HEX_QUOT
                    ) ?>;


    const depts = <?= json_encode(
                        array_map(
                            fn($d) => [
                                'id' => $d['id'],
                                'n'  => $d['department_name']
                            ],
                            $departments
                        ),
                        JSON_HEX_TAG |
                            JSON_HEX_AMP |
                            JSON_HEX_APOS |
                            JSON_HEX_QUOT
                    ) ?>;


    const projs = <?= json_encode(
                        array_map(
                            fn($p) => [
                                'id' => $p['id'],
                                'n'  => $p['project_name']
                            ],
                            $projects
                        ),
                        JSON_HEX_TAG |
                            JSON_HEX_AMP |
                            JSON_HEX_APOS |
                            JSON_HEX_QUOT
                    ) ?>;


    const ccs = <?= json_encode(
                    array_map(
                        fn($x) => [
                            'id' => $x['id'],
                            'n'  => $x['cost_center_name']
                        ],
                        $cCs
                    ),
                    JSON_HEX_TAG |
                        JSON_HEX_AMP |
                        JSON_HEX_APOS |
                        JSON_HEX_QUOT
                ) ?>;


    const existing = <?= json_encode(
                            $items,
                            JSON_HEX_TAG |
                                JSON_HEX_AMP |
                                JSON_HEX_APOS |
                                JSON_HEX_QUOT
                        ) ?>;


    /*
    |--------------------------------------------------------------------------
    | Escape HTML
    |--------------------------------------------------------------------------
    */

    function escapeHtml(value) {

        return String(value ?? '')
            .replace(
                /[&<>"']/g,
                character => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                })[character]
            );

    }


    /*
    |--------------------------------------------------------------------------
    | Options HTML
    |--------------------------------------------------------------------------
    */

    function optHtml(list, sel) {

        let h = '<option value="">—</option>';

        list.forEach(function(o) {

            h +=
                `<option value="${Number(o.id)}"
                ${o.id == sel ? 'selected' : ''}>
                ${escapeHtml(o.n)}
            </option>`;

        });

        return h;
    }


    /*
    |--------------------------------------------------------------------------
    | Renumber Lines
    |--------------------------------------------------------------------------
    */

    function renumberLines() {

        document
            .querySelectorAll('#linesTable tbody tr')
            .forEach(function(row, index) {

                row
                    .querySelectorAll('[data-line-field]')
                    .forEach(function(field) {

                        field.name =
                            `lines[${index}][${field.dataset.lineField}]`;

                    });

            });

    }


    /*
    |--------------------------------------------------------------------------
    | Remove Line
    |--------------------------------------------------------------------------
    */

    function removeLine(button) {

        const row = button.closest('tr');

        if (!row) {
            return;
        }

        /*
         * Remove jQuery validation errors
         */
        $(row)
            .find('label.error')
            .remove();

        row
            .querySelectorAll('.error, .valid')
            .forEach(function(field) {

                field.classList.remove('error');
                field.classList.remove('valid');

            });

        row.remove();

        renumberLines();

        calcAll();

    }


    /*
    |--------------------------------------------------------------------------
    | Add Line
    |--------------------------------------------------------------------------
    */

    function addLine(data) {

        data = data || {};

        const tb =
            document.querySelector('#linesTable tbody');

        const tr =
            document.createElement('tr');

        tr.className = 'border-b';


        const types = [
                'stock',
                'non_stock',
                'service',
                'asset',
                'expense'
            ]
            .map(function(t) {

                return `
                <option
                    value="${t}"
                    ${data.item_type === t ? 'selected' : ''}
                >
                    ${t}
                </option>
            `;

            })
            .join('');


        tr.innerHTML = `

        <!-- Type -->

        <td class="px-2 py-1">

            <select
                data-line-field="item_type"
                class="border rounded px-1 py-1 text-xs w-full"
            >
                ${types}
            </select>

        </td>


        <!-- Item -->

        <td class="px-2 py-1">

            <input
                type="text"
                data-line-field="item_name"
                value="${escapeHtml(data.item_name)}"
                placeholder="Item / service"
                maxlength="255"
                class="w-full border rounded px-2 py-1 text-sm line-item-name"
            >

            <input
                type="text"
                data-line-field="description"
                value="${escapeHtml(data.description)}"
                placeholder="Description (opt)"
                maxlength="500"
                class="w-full text-xs mt-1 border rounded px-2 py-1"
            >

        </td>


        <!-- Quantity -->

        <td class="px-2 py-1">

            <input
                type="number"
                step="0.001"
                min="0.001"
                data-line-field="quantity"
                value="${escapeHtml(data.quantity)}"
                class="w-full border rounded px-2 py-1 text-sm text-right lnQ"
            >

        </td>


        <!-- Unit -->

        <td class="px-2 py-1">

            <input
                type="text"
                data-line-field="unit"
                value="${escapeHtml(data.unit || 'pcs')}"
                maxlength="50"
                class="w-full border rounded px-2 py-1 text-sm line-unit"
            >

        </td>


        <!-- Unit Price -->

        <td class="px-2 py-1">

            <input
                type="number"
                step="0.01"
                min="0"
                data-line-field="est_unit_price"
                value="${escapeHtml(data.est_unit_price)}"
                class="w-full border rounded px-2 py-1 text-sm text-right lnP"
            >

        </td>


        <!-- Tax -->

        <td class="px-2 py-1">

            <input
                type="number"
                step="0.001"
                min="0"
                max="100"
                data-line-field="tax_percent"
                value="${escapeHtml(data.tax_percent ?? 0)}"
                class="w-full border rounded px-2 py-1 text-sm text-right lnT"
            >

        </td>


        <!-- Line Total -->

        <td class="px-2 py-1 text-right font-mono lnTot">
            0.00
        </td>


        <!-- Actions -->

        <td class="px-2 py-1">

            
            <input
                type="hidden"
                data-line-field="discount_amount"
                value="${escapeHtml(data.discount_amount ?? 0)}"
                class="lnD"
            >


            <select
                data-line-field="preferred_vendor_id"
                class="hidden"
            >
                ${optHtml(
                    vendors,
                    data.preferred_vendor_id
                )}
            </select>


            <select
                data-line-field="department_id"
                class="hidden"
            >
                ${optHtml(
                    depts,
                    data.department_id
                )}
            </select>


            <select
                data-line-field="project_id"
                class="hidden"
            >
                ${optHtml(
                    projs,
                    data.project_id
                )}
            </select>


            <select
                data-line-field="cost_center_id"
                class="hidden"
            >
                ${optHtml(
                    ccs,
                    data.cost_center_id
                )}
            </select>

            <button
                type="button"
                onclick="removeLine(this)"
                class="text-red-500"
                title="Remove"
            >
                &times;
            </button>

        </td>

    `;


        tb.appendChild(tr);

        renumberLines();

        calcAll();

        /*
         * Add validation rules to the new dynamic row
         */
        if (typeof window.applyLineValidation === 'function') {
            window.applyLineValidation(tr);
        }

    }


    /*
    |--------------------------------------------------------------------------
    | Calculate
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'input',
        function(e) {

            if (
                e.target.classList.contains('lnQ') ||
                e.target.classList.contains('lnP') ||
                e.target.classList.contains('lnT')
            ) {

                calcAll();

            }

        }
    );


    function calcAll() {

        let s = 0;

        document
            .querySelectorAll('#linesTable tbody tr')
            .forEach(function(tr) {

                const q =
                    parseFloat(
                        tr.querySelector('.lnQ').value
                    ) || 0;

                const p =
                    parseFloat(
                        tr.querySelector('.lnP').value
                    ) || 0;

                const t =
                    parseFloat(
                        tr.querySelector('.lnT').value
                    ) || 0;

                const d =
                    parseFloat(
                        tr.querySelector('.lnD').value
                    ) || 0;


                const taxable =
                    Math.max(
                        0,
                        (q * p) - d
                    );

                const tot =
                    taxable * (1 + t / 100);

                s += tot;


                tr.querySelector('.lnTot')
                    .textContent =
                    tot.toFixed(2);

            });


        document.getElementById('tSub')
            .textContent =
            s.toFixed(2);


        document.getElementById('browserTotal')
            .value =
            s.toFixed(2);

    }


    /*
    |--------------------------------------------------------------------------
    | Existing Items
    |--------------------------------------------------------------------------
    */

    existing.forEach(function(i) {

        addLine(i);

    });


    /*
    |--------------------------------------------------------------------------
    | Default Empty Rows
    |--------------------------------------------------------------------------
    */

    if (!existing.length) {

        addLine();
        // addLine();
        // addLine();

    }


    /*
    |--------------------------------------------------------------------------
    | jQuery Validation
    |--------------------------------------------------------------------------
    */
</script>


<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- jQuery Validation -->
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>


<script>
    $(document).ready(function() {

        window.addItemWithValidation = function() {

            const rows = $('#linesTable tbody tr');

            // No row exists
            if (!rows.length) {
                addLine();
                return;
            }

            // Validate the last/current item row
            const lastRow = rows.last()[0];

            // Make sure validation rules are attached
            if (typeof window.applyLineValidation === 'function') {
                window.applyLineValidation(lastRow);
            }

            let valid = true;

            const $row = $(lastRow);

            const itemType = $row.find('[data-line-field="item_type"]');
            const itemName = $row.find('[data-line-field="item_name"]');
            const quantity = $row.find('[data-line-field="quantity"]');
            const unit = $row.find('[data-line-field="unit"]');
            const price = $row.find('[data-line-field="est_unit_price"]');
            const tax = $row.find('[data-line-field="tax_percent"]');

            if (itemType.length && !itemType.valid()) {
                valid = false;
            }

            if (itemName.length && !itemName.valid()) {
                valid = false;
            }

            if (quantity.length && !quantity.valid()) {
                valid = false;
            }

            if (unit.length && !unit.valid()) {
                valid = false;
            }

            if (price.length && !price.valid()) {
                valid = false;
            }

            if (tax.length && !tax.valid()) {
                valid = false;
            }

            // Stop if current item is invalid
            if (!valid) {
                return false;
            }

            // Current item is valid -> add new row
            addLine();

            return true;
        };


        /*
        |--------------------------------------------------------------------------
        | Custom: Required Date
        |--------------------------------------------------------------------------
        */

        $.validator.addMethod(
            'validRequiredDate',
            function(value, element) {

                const requestDate =
                    $('#request_date').val();

                if (!value || !requestDate) {
                    return true;
                }

                return value >= requestDate;

            },
            'Required date cannot be before request date.'
        );


        /*
        |--------------------------------------------------------------------------
        | Custom: Tax
        |--------------------------------------------------------------------------
        */

        $.validator.addMethod(
            'validTax',
            function(value, element) {

                if (value === '') {
                    return true;
                }

                const tax =
                    parseFloat(value);

                return (
                    !isNaN(tax) &&
                    tax >= 0 &&
                    tax <= 100
                );

            },
            'Tax percentage must be between 0 and 100.'
        );


        /*
        |--------------------------------------------------------------------------
        | Form Validator
        |--------------------------------------------------------------------------
        */

        const validator =
            $('#purchaseRequisitionForm').validate({

                ignore: [],

                errorElement: 'label',

                errorClass: 'error',


                /*
                |--------------------------------------------------------------------------
                | Header Rules
                |--------------------------------------------------------------------------
                */

                rules: {

                    requester_id: {
                        required: true
                    },

                    company_id: {
                        required: true
                    },

                    department_id: {
                        required: true
                    },

                    request_date: {
                        required: true,
                        date: true
                    },

                    required_date: {
                        required: true,
                        date: true,
                        validRequiredDate: true
                    },

                    branch: {
                        maxlength: 255
                    },

                    purpose: {
                        maxlength: 500
                    },

                    description: {
                        maxlength: 2000
                    }

                },


                /*
                |--------------------------------------------------------------------------
                | Messages
                |--------------------------------------------------------------------------
                */

                messages: {

                    requester_id: {
                        required: 'Please select requester.'
                    },

                    company_id: {
                        required: 'Please select company.'
                    },

                    department_id: {
                        required: 'Please select department.'
                    },

                    request_date: {
                        required: 'Please select request date.',

                        date: 'Please enter a valid request date.'
                    },

                    required_date: {
                        required: 'Please select required date.',

                        date: 'Please enter a valid required date.'
                    },

                    branch: {
                        maxlength: 'Branch cannot exceed 255 characters.'
                    },

                    purpose: {
                        maxlength: 'Purpose cannot exceed 500 characters.'
                    },

                    description: {
                        maxlength: 'Description cannot exceed 2000 characters.'
                    }

                },


                /*
                |--------------------------------------------------------------------------
                | Error Placement
                |--------------------------------------------------------------------------
                */

                errorPlacement: function(error, element) {

                    if (element.closest('#linesTable').length) {

                        if (element.is('select')) {
                            error.insertAfter(element);
                        } else {
                            error.insertAfter(element);
                        }

                        return;
                    }

                    if (element.is('select')) {
                        error.appendTo(element.parent());
                    } else {
                        error.insertAfter(element);
                    }
                },


                /*
                |--------------------------------------------------------------------------
                | Highlight
                |--------------------------------------------------------------------------
                */

                highlight: function(
                    element
                ) {

                    $(element)
                        .addClass('error')
                        .removeClass('valid');

                },


                /*
                |--------------------------------------------------------------------------
                | Unhighlight
                |--------------------------------------------------------------------------
                */

                unhighlight: function(
                    element
                ) {

                    $(element)
                        .removeClass('error')
                        .addClass('valid');

                },


                /*
                |--------------------------------------------------------------------------
                | Submit
                |--------------------------------------------------------------------------
                */

                submitHandler: function(
                    form
                ) {

                    let valid =
                        validateAllLines();


                    if (!valid) {

                        return false;

                    }


                    form.submit();

                }

            });


        /*
        |--------------------------------------------------------------------------
        | Dynamic Line Validation
        |--------------------------------------------------------------------------
        */

        window.applyLineValidation = function(tr) {

            const $row = $(tr);

            const itemType = $row.find('[data-line-field="item_type"]');
            const itemName = $row.find('[data-line-field="item_name"]');
            const quantity = $row.find('[data-line-field="quantity"]');
            const unit = $row.find('[data-line-field="unit"]');
            const price = $row.find('[data-line-field="est_unit_price"]');
            const tax = $row.find('[data-line-field="tax_percent"]');

            itemType.rules('add', {
                required: true,
                messages: {
                    required: 'Please select item type.'
                }
            });

            itemName.rules('add', {
                required: true,
                minlength: 2,
                maxlength: 255,
                messages: {
                    required: 'Please enter item name.',
                    minlength: 'Item name must be at least 2 characters.',
                    maxlength: 'Item name cannot exceed 255 characters.'
                }
            });

            quantity.rules('add', {
                required: true,
                number: true,
                min: 0.001,
                messages: {
                    required: 'Please enter quantity.',
                    number: 'Please enter a valid quantity.',
                    min: 'Quantity must be greater than 0.'
                }
            });

            unit.rules('add', {
                required: true,
                messages: {
                    required: 'Please enter unit.'
                }
            });

            price.rules('add', {
                required: true,
                number: true,
                min: 0,
                messages: {
                    required: 'Please enter unit price.',
                    number: 'Please enter a valid price.',
                    min: 'Price cannot be negative.'
                }
            });

            tax.rules('add', {
                number: true,
                min: 0,
                max: 100,
                messages: {
                    number: 'Please enter a valid tax.',
                    min: 'Tax cannot be less than 0.',
                    max: 'Tax cannot exceed 100.'
                }
            });
        };

        /*
        |--------------------------------------------------------------------------
        | Validate All Dynamic Lines
        |--------------------------------------------------------------------------
        */

        function validateAllLines() {

            let valid = true;

            const rows =
                $('#linesTable tbody tr');


            /*
             * At least one line
             */

            if (rows.length === 0) {

                return false;

            }


            /*
             * Validate every dynamic field
             */

            rows.each(function() {

                const row =
                    $(this);


                const itemName =
                    row.find(
                        '[data-line-field="item_name"]'
                    );


                const quantity =
                    row.find(
                        '[data-line-field="quantity"]'
                    );


                const unit =
                    row.find(
                        '[data-line-field="unit"]'
                    );


                const price =
                    row.find(
                        '[data-line-field="est_unit_price"]'
                    );


                const tax =
                    row.find(
                        '[data-line-field="tax_percent"]'
                    );


                if (!itemName.valid()) {
                    valid = false;
                }


                if (!quantity.valid()) {
                    valid = false;
                }


                if (!unit.valid()) {
                    valid = false;
                }


                if (!price.valid()) {
                    valid = false;
                }


                if (!tax.valid()) {
                    valid = false;
                }

            });


            return valid;

        }


        /*
        |--------------------------------------------------------------------------
        | Request Date Change
        |--------------------------------------------------------------------------
        */

        $('#request_date').on(
            'change',
            function() {

                $('#required_date').valid();

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Required Date Change
        |--------------------------------------------------------------------------
        */

        $('#required_date').on(
            'change',
            function() {

                $(this).valid();

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Revalidate Dynamic Fields on Input
        |--------------------------------------------------------------------------
        */

        $(document).on(
            'input',
            '#linesTable input',
            function() {

                if ($(this).hasClass('error')) {

                    $(this).valid();

                }

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Validate Dynamic Selects
        |--------------------------------------------------------------------------
        */

        $(document).on(
            'change',
            '#linesTable select',
            function() {

                if ($(this).hasClass('error')) {

                    $(this).valid();

                }

            }
        );


        /*
        |--------------------------------------------------------------------------
        | Add Line Button
        |--------------------------------------------------------------------------
        */

        window.addLine = addLine;


        /*
        |--------------------------------------------------------------------------
        | Remove Line Button
        |--------------------------------------------------------------------------
        */

        window.removeLine = removeLine;



    });
</script>



<?php
require_once PROCUREMENT_APP_ROOT . '/includes/footer.php';
?>