<?php

/** F13 — Company Management */

$pageTitle = 'Companies';

require_once '../../../../includes/header.php';
require_once '../../../../helpers/redirect.php';
require_once '../../helpers/finance_helpers.php';
require_once __DIR__ . '/../IntercompanyService.php';


/* =========================================================
   Permission
========================================================= */

if (!hasPermission('company.view')) {
    die('Access Denied');
}

$db = getDB();


/* =========================================================
   POST ACTIONS
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* -----------------------------------------------------
       CSRF
    ----------------------------------------------------- */

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {

        flash('error', 'Invalid request.');

        to('modules/finance/group/companies/index.php');

        exit;
    }


    $action = $_POST['action'] ?? '';


    /* =====================================================
       CREATE COMPANY
    ===================================================== */

    if (
        $action === 'create' &&
        hasPermission('company.create')
    ) {

        $code = trim($_POST['company_code'] ?? '');
        $name = trim($_POST['company_name'] ?? '');

        $legalName = trim($_POST['legal_name'] ?? '');
        $registrationNumber = trim($_POST['registration_number'] ?? '');
        $taxNumber = trim($_POST['tax_number'] ?? '');
        $baseCurrency = strtoupper(
            trim($_POST['base_currency'] ?? 'AED')
        );
        $country = trim($_POST['country'] ?? '');
        $timezone = trim($_POST['timezone'] ?? '');


        /* -------------------------------------------------
           Server-side validation
        ------------------------------------------------- */

        $errors = [];


        /* Company Code */

        if ($code === '') {

            $errors[] = 'Company code is required.';
        } elseif (strlen($code) < 2) {

            $errors[] =
                'Company code must contain at least 2 characters.';
        } elseif (strlen($code) > 30) {

            $errors[] =
                'Company code cannot exceed 30 characters.';
        } elseif (!preg_match('/^[A-Za-z0-9_-]+$/', $code)) {

            $errors[] =
                'Company code contains invalid characters.';
        }


        /* Company Name */

        if ($name === '') {

            $errors[] = 'Company name is required.';
        } elseif (strlen($name) < 2) {

            $errors[] =
                'Company name must contain at least 2 characters.';
        } elseif (strlen($name) > 150) {

            $errors[] =
                'Company name cannot exceed 150 characters.';
        }


        /* Legal Name */

        if ($legalName !== '') {

            if (strlen($legalName) > 200) {

                $errors[] =
                    'Legal name cannot exceed 200 characters.';
            }
        }


        /* Registration Number */

        if ($registrationNumber !== '') {

            if (strlen($registrationNumber) > 100) {

                $errors[] =
                    'Registration number cannot exceed 100 characters.';
            } elseif (
                !preg_match(
                    '/^[A-Za-z0-9\-\/\s]+$/',
                    $registrationNumber
                )
            ) {

                $errors[] =
                    'Registration number contains invalid characters.';
            }
        }


        /* Tax Number */

        if ($taxNumber !== '') {

            if (strlen($taxNumber) > 100) {

                $errors[] =
                    'TRN / VAT number cannot exceed 100 characters.';
            } elseif (
                !preg_match(
                    '/^[A-Za-z0-9\-\/\s]+$/',
                    $taxNumber
                )
            ) {

                $errors[] =
                    'TRN / VAT number contains invalid characters.';
            }
        }


        /* Currency */

        $allowedCurrencies = [
            'AED',
            'USD',
            'EUR',
            'GBP',
            'SAR'
        ];

        if (!in_array($baseCurrency, $allowedCurrencies, true)) {

            $errors[] = 'Invalid currency selected.';
        }


        /* Country */

        if ($country !== '') {

            if (strlen($country) > 100) {

                $errors[] =
                    'Country cannot exceed 100 characters.';
            } elseif (
                !preg_match(
                    '/^[A-Za-zÀ-ÿ\s\-]+$/u',
                    $country
                )
            ) {

                $errors[] =
                    'Country contains invalid characters.';
            }
        }


        /* Timezone */

        if ($timezone !== '') {

            if (strlen($timezone) > 100) {

                $errors[] =
                    'Timezone cannot exceed 100 characters.';
            } elseif (
                !preg_match(
                    '/^[A-Za-z_]+\/[A-Za-z_]+(?:\/[A-Za-z_]+)?$/',
                    $timezone
                )
            ) {

                $errors[] =
                    'Invalid timezone format.';
            }
        }


        /* -------------------------------------------------
           Duplicate validation
        ------------------------------------------------- */

        if (empty($errors)) {

            $stmt = $db->prepare("
                SELECT id
                FROM companies
                WHERE company_code = ?
                LIMIT 1
            ");

            $stmt->execute([$code]);

            if ($stmt->fetch()) {

                $errors[] =
                    'Company code already exists.';
            }
        }


        if (empty($errors)) {

            $stmt = $db->prepare("
                SELECT id
                FROM companies
                WHERE company_name = ?
                LIMIT 1
            ");

            $stmt->execute([$name]);

            if ($stmt->fetch()) {

                $errors[] =
                    'Company name already exists.';
            }
        }


        /* -------------------------------------------------
           Insert
        ------------------------------------------------- */

        if (!empty($errors)) {

            foreach ($errors as $error) {

                flash('error', $error);
            }
        } else {

            try {

                $stmt = $db->prepare("
                    INSERT INTO companies
                    (
                        company_code,
                        company_name,
                        legal_name,
                        registration_number,
                        tax_number,
                        base_currency,
                        country,
                        timezone,
                        status
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )
                ");


                $stmt->execute([
                    $code,
                    $name,
                    $legalName !== '' ? $legalName : null,
                    $registrationNumber !== ''
                        ? $registrationNumber
                        : null,
                    $taxNumber !== ''
                        ? $taxNumber
                        : null,
                    $baseCurrency,
                    $country !== '' ? $country : null,
                    $timezone !== '' ? $timezone : null,
                    'active'
                ]);


                $companyId =
                    (int)$db->lastInsertId();


                /* -------------------------------------------------
                   Audit Log
                ------------------------------------------------- */

                IntercompanyService::log(
                    $db,
                    'company_created',
                    $companyId,
                    null,
                    $name,
                    null,
                    $_SESSION['user_id']
                );


                flash(
                    'success',
                    'Company created successfully.'
                );
            } catch (PDOException $e) {

                if (
                    $e->getCode() === '23000'
                ) {

                    flash(
                        'error',
                        'Company code or company name already exists.'
                    );
                } else {

                    flash(
                        'error',
                        'Create failed. Please try again.'
                    );
                }
            }
        }


        /* =====================================================
       TOGGLE COMPANY STATUS
    ===================================================== */
    } elseif (
        $action === 'toggle' &&
        hasPermission('company.edit')
    ) {

        $companyId =
            (int)($_POST['id'] ?? 0);


        if ($companyId <= 0) {

            flash(
                'error',
                'Invalid company.'
            );
        } else {

            try {

                /*
                 * Default company cannot be deactivated.
                 */

                $stmt = $db->prepare("
                    UPDATE companies
                    SET status =
                        IF(
                            status = 'active',
                            'inactive',
                            'active'
                        )
                    WHERE id = ?
                      AND is_default = 0
                ");

                $stmt->execute([
                    $companyId
                ]);


                if ($stmt->rowCount() > 0) {

                    flash(
                        'success',
                        'Company status updated successfully.'
                    );
                } else {

                    flash(
                        'error',
                        'Company could not be updated.'
                    );
                }
            } catch (PDOException $e) {

                flash(
                    'error',
                    'Unable to update company status.'
                );
            }
        }
    }


    to('modules/finance/group/companies/index.php');

    exit;
}


/* =========================================================
   FETCH COMPANIES
========================================================= */

$companies = $db->query("
    SELECT *
    FROM companies
    ORDER BY id
")->fetchAll();

?>

<!-- =======================================================
     PAGE HEADER
======================================================== -->

<div class="mb-6 flex justify-between items-center flex-wrap gap-3">

    <div>

        <h1 class="text-2xl font-bold text-gray-800">
            Companies
        </h1>

        <p class="text-gray-600 text-sm mt-1">
            Legal entities — each with isolated ledgers
            via company-owned financial years
        </p>

    </div>


    <div class="flex gap-2">

        <?php if (hasPermission('company.create')): ?>

            <button
                type="button"
                data-modal-open="createModal"
                class="bg-blue-600
                       text-white
                       px-4 py-2
                       text-sm
                       hover:bg-blue-700">

                <i class="fas fa-plus mr-1"></i>

                New Company

            </button>

        <?php endif; ?>


        <a
            href="../index.php"
            class="bg-white
                   border border-gray-300
                   px-3 py-2
                   text-sm
                   text-gray-700
                   hover:bg-gray-50">

            <i class="fas fa-arrow-left mr-1"></i>

            Back

        </a>

    </div>

</div>


<!-- =======================================================
     COMPANY TABLE
======================================================== -->

<div
    class="bg-white
           border border-gray-200
           shadow-sm
           overflow-x-auto">

    <table class="w-full text-sm">

        <thead
            class="bg-gray-50
                   text-gray-600
                   uppercase
                   text-xs
                   border-b
                   border-gray-200">

            <tr>

                <th class="px-4 py-3 text-left">
                    Code
                </th>

                <th class="px-4 py-3 text-left">
                    Name / Legal Name
                </th>

                <th class="px-4 py-3 text-left">
                    Tax #
                </th>

                <th class="px-4 py-3 text-left">
                    Currency
                </th>

                <th class="px-4 py-3 text-left">
                    Country
                </th>

                <th class="px-4 py-3 text-left">
                    Status
                </th>

                <th class="px-4 py-3 text-right">
                    Action
                </th>

            </tr>

        </thead>


        <tbody class="divide-y divide-gray-100">

            <?php if (empty($companies)): ?>

                <tr>

                    <td
                        colspan="7"
                        class="px-4 py-10
                               text-center
                               text-gray-500">

                        <i
                            class="fas fa-building
                                   text-2xl
                                   text-gray-300
                                   mb-2">
                        </i>

                        <div>
                            No companies found.
                        </div>

                    </td>

                </tr>

            <?php else: ?>

                <?php foreach ($companies as $c): ?>

                    <tr
                        class="hover:bg-gray-50">

                        <!-- Code -->

                        <td
                            class="px-4 py-3
                                   font-mono
                                   text-sm
                                   text-gray-700">

                            <?= sanitize(
                                $c['company_code']
                            ) ?>

                            <?php if (!empty($c['is_default'])): ?>

                                <span
                                    class="ml-1
                                           text-[10px]
                                           px-1.5
                                           py-0.5
                                           bg-blue-100
                                           text-blue-700">

                                    DEFAULT

                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- Name -->

                        <td class="px-4 py-3">

                            <div
                                class="font-medium
                                       text-gray-800">

                                <?= sanitize(
                                    $c['company_name']
                                ) ?>

                            </div>

                            <?php if (!empty($c['legal_name'])): ?>

                                <div
                                    class="text-xs
                                           text-gray-400
                                           mt-0.5">

                                    <?= sanitize(
                                        $c['legal_name']
                                    ) ?>

                                </div>

                            <?php endif; ?>

                        </td>


                        <!-- Tax -->

                        <td
                            class="px-4 py-3
                                   text-xs
                                   text-gray-600">

                            <?= sanitize(
                                $c['tax_number'] ?? ''
                            ) ?>

                        </td>


                        <!-- Currency -->

                        <td
                            class="px-4 py-3
                                   font-medium
                                   text-gray-700">

                            <?= sanitize(
                                $c['base_currency']
                            ) ?>

                        </td>


                        <!-- Country -->

                        <td
                            class="px-4 py-3
                                   text-gray-600">

                            <?= sanitize(
                                $c['country'] ?? ''
                            ) ?>

                        </td>


                        <!-- Status -->

                        <td class="px-4 py-3">

                            <?php if ($c['status'] === 'active'): ?>

                                <span
                                    class="inline-flex
                                           items-center
                                           px-2
                                           py-1
                                           text-xs
                                           font-medium
                                           bg-green-100
                                           text-green-700">

                                    <span
                                        class="w-1.5
                                               h-1.5
                                               bg-green-600
                                               mr-1.5">
                                    </span>

                                    ACTIVE

                                </span>

                            <?php else: ?>

                                <span
                                    class="inline-flex
                                           items-center
                                           px-2
                                           py-1
                                           text-xs
                                           font-medium
                                           bg-gray-100
                                           text-gray-500">

                                    <span
                                        class="w-1.5
                                               h-1.5
                                               bg-gray-400
                                               mr-1.5">
                                    </span>

                                    INACTIVE

                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- Action -->

                        <td
                            class="px-4 py-3
                                   text-right">

                            <?php
                            if (
                                !$c['is_default'] &&
                                hasPermission('company.edit')
                            ):
                            ?>

                                <form
                                    method="post"
                                    class="inline company-toggle-form">

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars(
                                                    csrfToken(),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>">

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="toggle">

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$c['id'] ?>">

                                    <button
                                        type="submit"
                                        class="text-xs
                                               font-medium
                                               <?= $c['status'] === 'active'
                                                    ? 'text-orange-600 hover:text-orange-700'
                                                    : 'text-green-600 hover:text-green-700'
                                                ?>">

                                        <?= $c['status'] === 'active'
                                            ? 'Deactivate'
                                            : 'Activate'
                                        ?>

                                    </button>

                                </form>

                            <?php else: ?>

                                <span
                                    class="text-xs
                                           text-gray-400">

                                    —

                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

        </tbody>

    </table>

</div>


<?php if (hasPermission('company.create')): ?>

    <!-- =======================================================
     CREATE COMPANY MODAL
======================================================== -->

    <div
        id="createModal"
        class="hidden
           fixed
           inset-0
           z-50
           bg-black/40
           flex
           items-start
           justify-center
           pt-16
           px-4
           overflow-y-auto">

        <div
            class="bg-white
               w-full
               max-w-lg
               shadow-xl
               border
               border-gray-200
               mb-10">


            <!-- =================================================
             MODAL HEADER
        ================================================== -->

            <div
                class="px-5
                   py-3
                   border-b
                   border-gray-200
                   bg-gray-50">

                <div
                    class="flex
                       items-center
                       justify-between">

                    <div>

                        <h2
                            class="text-sm
                               font-semibold
                               text-gray-800">

                            New Company

                        </h2>

                        <p
                            class="text-xs
                               text-gray-500
                               mt-0.5">

                            Enter company and registration details

                        </p>

                    </div>


                    <button
                        type="button"
                        data-modal-close
                        class="w-7
                           h-7
                           flex
                           items-center
                           justify-center
                           text-gray-400
                           hover:text-gray-700
                           hover:bg-gray-200
                           text-lg
                           leading-none">

                        &times;

                    </button>

                </div>

            </div>


            <!-- =================================================
             FORM
        ================================================== -->

            <form
                method="post"
                id="createCompanyForm"
                class="p-5"
                novalidate>


                <!-- CSRF -->

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                                csrfToken(),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>">

                <input
                    type="hidden"
                    name="action"
                    value="create">


                <!-- =================================================
                 BASIC INFORMATION
            ================================================== -->

                <div class="mb-5">

                    <div
                        class="text-xs
                           font-semibold
                           text-gray-700
                           uppercase
                           tracking-wide
                           mb-3">

                        Basic Information

                    </div>


                    <div
                        class="grid
                           grid-cols-1
                           sm:grid-cols-2
                           gap-4">


                        <!-- Company Code -->

                        <div class="form-field">

                            <label
                                for="company_code"
                                class="block
                                   text-xs
                                   font-medium
                                   text-gray-600
                                   mb-1.5">

                                Company Code

                                <span class="text-red-600">*</span>

                            </label>

                            <input
                                type="text"
                                name="company_code"
                                id="company_code"
                                maxlength="30"
                                autocomplete="off"
                                placeholder="e.g. SUB02"
                                class="w-full
                                   h-10
                                   border
                                   border-gray-300
                                   px-3
                                   text-sm
                                   uppercase
                                   focus:outline-none
                                   focus:border-blue-500
                                   focus:ring-1
                                   focus:ring-blue-500">

                        </div>


                        <!-- Company Name -->

                        <div class="form-field">

                            <label
                                for="company_name"
                                class="block
                                   text-xs
                                   font-medium
                                   text-gray-600
                                   mb-1.5">

                                Trading Name

                                <span class="text-red-600">*</span>

                            </label>

                            <input
                                type="text"
                                name="company_name"
                                id="company_name"
                                maxlength="150"
                                autocomplete="off"
                                placeholder="Trading name"
                                class="w-full
                                   h-10
                                   border
                                   border-gray-300
                                   px-3
                                   text-sm
                                   focus:outline-none
                                   focus:border-blue-500
                                   focus:ring-1
                                   focus:ring-blue-500">

                        </div>

                    </div>

                </div>


                <!-- =================================================
                 LEGAL INFORMATION
            ================================================== -->

                <div class="mb-5">

                    <div
                        class="text-xs
                           font-semibold
                           text-gray-700
                           uppercase
                           tracking-wide
                           mb-3">

                        Legal Information

                    </div>


                    <!-- Legal Name -->

                    <div
                        class="form-field
                           mb-4">

                        <label
                            for="legal_name"
                            class="block
                               text-xs
                               font-medium
                               text-gray-600
                               mb-1.5">

                            Legal Name

                        </label>

                        <input
                            type="text"
                            name="legal_name"
                            id="legal_name"
                            maxlength="200"
                            autocomplete="off"
                            placeholder="Registered legal name"
                            class="w-full
                               h-10
                               border
                               border-gray-300
                               px-3
                               text-sm
                               focus:outline-none
                               focus:border-blue-500
                               focus:ring-1
                               focus:ring-blue-500">

                    </div>


                    <div
                        class="grid
                           grid-cols-1
                           sm:grid-cols-2
                           gap-4">


                        <!-- Registration Number -->

                        <div class="form-field">

                            <label
                                for="registration_number"
                                class="block
                                   text-xs
                                   font-medium
                                   text-gray-600
                                   mb-1.5">

                                Registration Number

                            </label>

                            <input
                                type="text"
                                name="registration_number"
                                id="registration_number"
                                maxlength="100"
                                autocomplete="off"
                                placeholder="Registration number"
                                class="w-full
                                   h-10
                                   border
                                   border-gray-300
                                   px-3
                                   text-sm
                                   focus:outline-none
                                   focus:border-blue-500
                                   focus:ring-1
                                   focus:ring-blue-500">

                        </div>


                        <!-- Tax Number -->

                        <div class="form-field">

                            <label
                                for="tax_number"
                                class="block
                                   text-xs
                                   font-medium
                                   text-gray-600
                                   mb-1.5">

                                TRN / VAT Number

                            </label>

                            <input
                                type="text"
                                name="tax_number"
                                id="tax_number"
                                maxlength="100"
                                autocomplete="off"
                                placeholder="TRN / VAT number"
                                class="w-full
                                   h-10
                                   border
                                   border-gray-300
                                   px-3
                                   text-sm
                                   focus:outline-none
                                   focus:border-blue-500
                                   focus:ring-1
                                   focus:ring-blue-500">

                        </div>

                    </div>

                </div>


                <!-- =================================================
                 LOCATION & CURRENCY
            ================================================== -->

                <div class="mb-5">

                    <div
                        class="text-xs
                           font-semibold
                           text-gray-700
                           uppercase
                           tracking-wide
                           mb-3">

                        Location & Currency

                    </div>


                    <div
                        class="grid
                           grid-cols-1
                           sm:grid-cols-3
                           gap-4">


                        <!-- Currency -->

                        <div class="form-field">

                            <label
                                for="base_currency"
                                class="block
                                   text-xs
                                   font-medium
                                   text-gray-600
                                   mb-1.5">

                                Currency

                                <span class="text-red-600">*</span>

                            </label>


                            <div class="relative">

                                <select
                                    name="base_currency"
                                    id="base_currency"
                                    class="w-full
                                       h-10
                                       appearance-none
                                       border
                                       border-gray-300
                                       bg-white
                                       px-3
                                       pr-8
                                       text-sm
                                       cursor-pointer
                                       focus:outline-none
                                       focus:border-blue-500
                                       focus:ring-1
                                       focus:ring-blue-500">

                                    <option value="">
                                        Select Currency
                                    </option>

                                    <?php foreach (
                                        [
                                            'AED',
                                            'USD',
                                            'EUR',
                                            'GBP',
                                            'SAR'
                                        ] as $cu
                                    ): ?>

                                        <option
                                            value="<?= $cu ?>">

                                            <?= $cu ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>


                                <span
                                    class="pointer-events-none
                                       absolute
                                       right-3
                                       top-1/2
                                       -translate-y-1/2
                                       text-gray-500">

                                    <i
                                        class="fas fa-chevron-down text-xs">
                                    </i>

                                </span>

                            </div>

                        </div>


                        <!-- Country -->

                        <div class="form-field">

                            <label
                                for="country"
                                class="block
                                   text-xs
                                   font-medium
                                   text-gray-600
                                   mb-1.5">

                                Country

                            </label>

                            <input
                                type="text"
                                name="country"
                                id="country"
                                maxlength="100"
                                autocomplete="off"
                                placeholder="Country"
                                class="w-full
                                   h-10
                                   border
                                   border-gray-300
                                   px-3
                                   text-sm
                                   focus:outline-none
                                   focus:border-blue-500
                                   focus:ring-1
                                   focus:ring-blue-500">

                        </div>


                        <!-- Timezone -->

                        <div class="form-field">

                            <label
                                for="timezone"
                                class="block
                                   text-xs
                                   font-medium
                                   text-gray-600
                                   mb-1.5">

                                Timezone

                            </label>

                            <input
                                type="text"
                                name="timezone"
                                id="timezone"
                                maxlength="100"
                                autocomplete="off"
                                placeholder="e.g. Asia/Dubai"
                                class="w-full
                                   h-10
                                   border
                                   border-gray-300
                                   px-3
                                   text-sm
                                   focus:outline-none
                                   focus:border-blue-500
                                   focus:ring-1
                                   focus:ring-blue-500">

                        </div>

                    </div>

                </div>


                <!-- =================================================
                 MODAL FOOTER
            ================================================== -->

                <div
                    class="flex
                       items-center
                       justify-end
                       gap-2
                       pt-4
                       border-t
                       border-gray-200">


                    <!-- Cancel -->

                    <button
                        type="button"
                        data-modal-close
                        class="h-9
                           px-4
                           border
                           border-gray-300
                           bg-white
                           text-gray-700
                           text-sm
                           hover:bg-gray-50">

                        Cancel

                    </button>


                    <!-- Create -->

                    <button
                        type="submit"
                        class="h-9
                           px-5
                           bg-blue-600
                           text-white
                           text-sm
                           font-medium
                           hover:bg-blue-700">

                        <i class="fas fa-plus mr-1.5"></i>

                        Create

                    </button>

                </div>

            </form>

        </div>

    </div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>


    <!-- =======================================================
     VALIDATION CSS
======================================================== -->

    <style>
        /*
     * Validation message
     */

        #createCompanyForm label.error {

            display: block !important;

            width: 100%;

            clear: both;

            margin-top: 4px !important;

            margin-bottom: 0 !important;

            color: #dc2626;

            font-size: 11px;

            line-height: 15px;

            font-weight: 400;
        }


        /*
     * Invalid field
     */

        #createCompanyForm input.error,
        #createCompanyForm select.error {

            border-color: #ef4444 !important;

            box-shadow: none !important;
        }


        /*
     * Valid field
     */

        #createCompanyForm input.valid,
        #createCompanyForm select.valid {

            border-color: #d1d5db !important;

            box-shadow: none !important;
        }


        /*
     * Remove default select arrow
     */

        #createCompanyForm select {

            -webkit-appearance: none;

            -moz-appearance: none;

            appearance: none;

            background-image: none !important;
        }
    </style>


    <!-- =======================================================
     MODAL + VALIDATION JAVASCRIPT
======================================================== -->

    <script>
        $(document).ready(function() {


            /* =====================================================
               CUSTOM VALIDATION METHODS
            ===================================================== */


            /*
             * Company Code
             *
             * Allowed:
             * A-Z
             * a-z
             * 0-9
             * _
             * -
             */

            $.validator.addMethod(
                'companyCodeFormat',
                function(value, element) {

                    if (!value) {
                        return true;
                    }

                    return /^[A-Za-z0-9_-]+$/.test(value);

                },
                'Only letters, numbers, hyphen and underscore are allowed.'
            );


            /*
             * Company / Legal Name
             */

            $.validator.addMethod(
                'companyNameFormat',
                function(value, element) {

                    if (!value) {
                        return true;
                    }

                    return /^[A-Za-z0-9À-ÿ&.,'()\/\-\s]+$/u.test(value);

                },
                'Please enter a valid name.'
            );


            /*
             * Registration Number
             */

            $.validator.addMethod(
                'registrationFormat',
                function(value, element) {

                    if (!value) {
                        return true;
                    }

                    return /^[A-Za-z0-9\-\/\s]+$/.test(value);

                },
                'Only letters, numbers, spaces, hyphen and slash are allowed.'
            );


            /*
             * Tax Number
             */

            $.validator.addMethod(
                'taxNumberFormat',
                function(value, element) {

                    if (!value) {
                        return true;
                    }

                    return /^[A-Za-z0-9\-\/\s]+$/.test(value);

                },
                'Only letters, numbers, spaces, hyphen and slash are allowed.'
            );


            /*
             * Country
             */

            $.validator.addMethod(
                'countryFormat',
                function(value, element) {

                    if (!value) {
                        return true;
                    }

                    return /^[A-Za-zÀ-ÿ\s\-]+$/u.test(value);

                },
                'Please enter a valid country name.'
            );


            /*
             * Timezone
             *
             * Example:
             * Asia/Dubai
             * Asia/Kolkata
             * Europe/London
             */

            $.validator.addMethod(
                'timezoneFormat',
                function(value, element) {

                    if (!value) {
                        return true;
                    }

                    return /^[A-Za-z_]+\/[A-Za-z_]+(?:\/[A-Za-z_]+)?$/.test(value);

                },
                'Enter a valid timezone, e.g. Asia/Dubai.'
            );


            /* =====================================================
               COMPANY FORM VALIDATION
            ===================================================== */

            $('#createCompanyForm').validate({

                ignore: [],

                errorElement: 'label',

                errorClass: 'error',


                rules: {

                    company_code: {

                        required: true,

                        minlength: 2,

                        maxlength: 30,

                        companyCodeFormat: true
                    },


                    company_name: {

                        required: true,

                        minlength: 2,

                        maxlength: 150,

                        companyNameFormat: true
                    },


                    legal_name: {

                        minlength: 2,

                        maxlength: 200,

                        companyNameFormat: true
                    },


                    registration_number: {

                        maxlength: 100,

                        registrationFormat: true
                    },


                    tax_number: {

                        maxlength: 100,

                        taxNumberFormat: true
                    },


                    base_currency: {

                        required: true
                    },


                    country: {

                        maxlength: 100,

                        countryFormat: true
                    },


                    timezone: {

                        maxlength: 100,

                        timezoneFormat: true
                    }

                },


                messages: {

                    company_code: {

                        required: 'Company code is required.',

                        minlength: 'Company code must contain at least 2 characters.',

                        maxlength: 'Company code cannot exceed 30 characters.',

                        companyCodeFormat: 'Only letters, numbers, hyphen and underscore are allowed.'
                    },


                    company_name: {

                        required: 'Trading name is required.',

                        minlength: 'Trading name must contain at least 2 characters.',

                        maxlength: 'Trading name cannot exceed 150 characters.',

                        companyNameFormat: 'Please enter a valid company name.'
                    },


                    legal_name: {

                        minlength: 'Legal name must contain at least 2 characters.',

                        maxlength: 'Legal name cannot exceed 200 characters.',

                        companyNameFormat: 'Please enter a valid legal name.'
                    },


                    registration_number: {

                        maxlength: 'Registration number cannot exceed 100 characters.',

                        registrationFormat: 'Only letters, numbers, spaces, hyphen and slash are allowed.'
                    },


                    tax_number: {

                        maxlength: 'TRN / VAT number cannot exceed 100 characters.',

                        taxNumberFormat: 'Only letters, numbers, spaces, hyphen and slash are allowed.'
                    },


                    base_currency: {

                        required: 'Please select a currency.'
                    },


                    country: {

                        maxlength: 'Country cannot exceed 100 characters.',

                        countryFormat: 'Please enter a valid country name.'
                    },


                    timezone: {

                        maxlength: 'Timezone cannot exceed 100 characters.',

                        timezoneFormat: 'Enter a valid timezone, e.g. Asia/Dubai.'
                    }

                },


                /*
                 * Error message placement
                 */

                errorPlacement: function(error, element) {

                    error.css({

                        'display': 'block',

                        'width': '100%',

                        'clear': 'both'

                    });


                    /*
                     * For select, put error after
                     * the select wrapper.
                     */

                    if (
                        element.is('select') &&
                        element.parent('.relative').length
                    ) {

                        error.insertAfter(
                            element.parent('.relative')
                        );

                    } else {

                        error.insertAfter(element);
                    }

                },


                /*
                 * Remove error
                 */

                success: function(label) {

                    label.remove();

                },


                /*
                 * Highlight invalid
                 */

                highlight: function(element) {

                    $(element)
                        .addClass('error')
                        .removeClass('valid');

                },


                /*
                 * Valid field
                 */

                unhighlight: function(element) {

                    $(element)
                        .removeClass('error')
                        .addClass('valid');

                },


                /*
                 * Submit
                 */

                submitHandler: function(form) {

                    form.submit();

                }

            });


            /* =====================================================
               COMPANY CODE UPPERCASE
            ===================================================== */

            $('#company_code').on('input', function() {

                let value = $(this).val();

                $(this).val(
                    value.toUpperCase()
                );

            });


            /* =====================================================
               TRIM INPUT
            ===================================================== */

            $('#createCompanyForm input[type="text"]').on(
                'blur',
                function() {

                    $(this).val(
                        $.trim($(this).val())
                    );

                }
            );


            /* =====================================================
               CURRENCY VALIDATION ON CHANGE
            ===================================================== */

            $('#base_currency').on(
                'change',
                function() {

                    $(this).valid();

                }
            );


            /* =====================================================
               MODAL OPEN
            ===================================================== */

            document
                .querySelectorAll('[data-modal-open]')
                .forEach(function(element) {

                    element.addEventListener(
                        'click',
                        function(event) {

                            event.preventDefault();

                            const modalId =
                                element.getAttribute(
                                    'data-modal-open'
                                );

                            const modal =
                                document.getElementById(
                                    modalId
                                );

                            if (modal) {

                                modal.classList.remove(
                                    'hidden'
                                );

                                /*
                                 * Focus first input
                                 */

                                setTimeout(function() {

                                    $('#company_code').trigger(
                                        'focus'
                                    );

                                }, 100);

                            }

                        }
                    );

                });


            /* =====================================================
               MODAL CLOSE
            ===================================================== */

            document
                .querySelectorAll('[data-modal-close]')
                .forEach(function(element) {

                    element.addEventListener(
                        'click',
                        function() {

                            const modal =
                                element.closest('.fixed');

                            if (modal) {

                                modal.classList.add(
                                    'hidden'
                                );

                            }

                        }
                    );

                });


            /* =====================================================
               CLOSE WHEN CLICK OUTSIDE MODAL
            ===================================================== */

            const createModal =
                document.getElementById(
                    'createModal'
                );

            if (createModal) {

                createModal.addEventListener(
                    'click',
                    function(event) {

                        if (
                            event.target === createModal
                        ) {

                            createModal.classList.add(
                                'hidden'
                            );

                        }

                    }
                );

            }


            /* =====================================================
               ESC KEY CLOSE
            ===================================================== */

            document.addEventListener(
                'keydown',
                function(event) {

                    if (
                        event.key === 'Escape' &&
                        createModal &&
                        !createModal.classList.contains('hidden')
                    ) {

                        createModal.classList.add(
                            'hidden'
                        );

                    }

                }
            );


            /* =====================================================
               CLEAR FORM WHEN MODAL OPENS
            ===================================================== */

            $('[data-modal-open="createModal"]').on(
                'click',
                function() {

                    const form =
                        $('#createCompanyForm');

                    form[0].reset();

                    form.validate().resetForm();

                    form.find('.error')
                        .removeClass('error');

                    form.find('.valid')
                        .removeClass('valid');

                }
            );


        });
    </script>

<?php endif; ?>


<?php
require_once '../../../../includes/footer.php';
?>