<?php

$pageTitle = 'Edit Project';

require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('projects.edit')) {
    die('Access Denied');
}

$db = getDB();

$errors = restoreFormErrors();

if (empty($errors)) {
    clearOldInput();
}


/*
|--------------------------------------------------------------------------
| GET PROJECT ID
|--------------------------------------------------------------------------
*/

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    flash('error', 'Invalid Project ID.');
    to('modules/projects/index.php');
}


/*
|--------------------------------------------------------------------------
| GET PROJECT
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT *
    FROM projects
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$project = $stmt->fetch();

if (!$project) {
    flash('error', 'Project not found.');
    to('modules/projects/index.php');
}


/*
|--------------------------------------------------------------------------
| GET ACTIVE PROJECT MANAGERS
|--------------------------------------------------------------------------
*/

$managers = $db->query("
    SELECT id, first_name, last_name
    FROM employees
    WHERE status = 'active'
    ORDER BY first_name
")->fetchAll();


/*
|--------------------------------------------------------------------------
| UPDATE PROJECT
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $data = [
        'project_name'       => trim($_POST['project_name'] ?? ''),
        'client_name'        => trim($_POST['client_name'] ?? ''),
        'description'        => trim($_POST['description'] ?? ''),
        'start_date'         => $_POST['start_date'] ?? '',
        'end_date'           => $_POST['end_date'] ?? '',
        'estimated_hours'    => $_POST['estimated_hours'] ?? '',
        'project_manager_id' => $_POST['project_manager_id'] ?? '',
        'status'             => $_POST['status'] ?? 'planning',
        'priority'           => $_POST['priority'] ?? 'medium'
    ];


    /*
    |--------------------------------------------------------------------------
    | PHP VALIDATION
    |--------------------------------------------------------------------------
    */

    $errors = validate($data, [
        'project_name' => 'required|min:3|max:200',
        'start_date'   => 'required',
        'end_date'     => 'required'
    ]);


    /*
    |--------------------------------------------------------------------------
    | DATE VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        !empty($data['start_date']) &&
        !empty($data['end_date']) &&
        $data['end_date'] < $data['start_date']
    ) {
        $errors['end_date'] = 'End Date cannot be before Start Date.';
    }


    /*
    |--------------------------------------------------------------------------
    | ESTIMATED HOURS VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($data['estimated_hours'] !== '') {

        if (!is_numeric($data['estimated_hours'])) {

            $errors['estimated_hours'] =
                'Estimated Hours must be a valid number.';

        } elseif ((float)$data['estimated_hours'] < 0) {

            $errors['estimated_hours'] =
                'Estimated Hours cannot be negative.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | IF ERRORS
    |--------------------------------------------------------------------------
    */

    if (!empty($errors)) {

        saveFormState($errors);

        to('modules/projects/edit.php?id=' . $id);
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    $stmt = $db->prepare("
        UPDATE projects
        SET
            project_name = ?,
            client_name = ?,
            description = ?,
            start_date = ?,
            end_date = ?,
            estimated_hours = ?,
            project_manager_id = ?,
            status = ?,
            priority = ?
        WHERE id = ?
    ");


    $stmt->execute([

        $data['project_name'],

        $data['client_name'] !== ''
            ? $data['client_name']
            : null,

        $data['description'] !== ''
            ? $data['description']
            : null,

        $data['start_date'],

        $data['end_date'],

        $data['estimated_hours'] !== ''
            ? $data['estimated_hours']
            : null,

        $data['project_manager_id'] !== ''
            ? $data['project_manager_id']
            : null,

        $data['status'],

        $data['priority'],

        $id
    ]);


    /*
    |--------------------------------------------------------------------------
    | AUDIT LOG
    |--------------------------------------------------------------------------
    */

    logAudit(
        $_SESSION['user_id'],
        'update_project',
        'projects'
    );


    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */

    flash(
        'success',
        'Project updated successfully.'
    );

    to('modules/projects/index.php');
}

?>

<div class="mb-6">

    <a href="<?= url('modules/projects/index.php') ?>"
        class="text-blue-600 hover:text-blue-900">

        <i class="fas fa-arrow-left mr-1"></i>
        Back to Projects

    </a>

</div>


<div class="bg-white rounded-lg shadow p-6">

    <h2 class="text-xl font-semibold mb-6">
        Edit Project
    </h2>


    <?php if ($errors): ?>

        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">

            <ul class="list-disc list-inside">

                <?php foreach ($errors as $error): ?>

                    <li>
                        <?= sanitize($error) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <form
        method="POST"
        id="editProjectForm"
        class="space-y-4"
        novalidate
    >

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">


            <!-- PROJECT NAME -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Project Name

                    <span class="text-red-600">*</span>

                </label>

                <input
                    type="text"
                    name="project_name"
                    value="<?= old('project_name', $project['project_name']) ?>"
                    required
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                >

            </div>


            <!-- CLIENT NAME -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Client Name

                </label>

                <input
                    type="text"
                    name="client_name"
                    value="<?= old('client_name', $project['client_name']) ?>"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                >

            </div>


            <!-- START DATE -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Start Date

                    <span class="text-red-600">*</span>

                </label>

                <input
                    type="date"
                    name="start_date"
                    value="<?= old('start_date', $project['start_date']) ?>"
                    required
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                >

            </div>


            <!-- END DATE -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    End Date

                    <span class="text-red-600">*</span>

                </label>

                <input
                    type="date"
                    name="end_date"
                    value="<?= old('end_date', $project['end_date']) ?>"
                    required
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                >

            </div>


            <!-- ESTIMATED HOURS -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Estimated Hours

                </label>

                <input
                    type="number"
                    name="estimated_hours"
                    value="<?= old('estimated_hours', $project['estimated_hours']) ?>"
                    min="0"
                    step="any"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                >

            </div>


            <!-- PROJECT MANAGER -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Project Manager

                </label>

                <select
                    name="project_manager_id"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                >

                    <option value="">
                        Select Manager
                    </option>

                    <?php foreach ($managers as $mgr): ?>

                        <option
                            value="<?= $mgr['id'] ?>"
                            <?= old(
                                'project_manager_id',
                                $project['project_manager_id']
                            ) == $mgr['id']
                                ? 'selected'
                                : '' ?>
                        >

                            <?= sanitize(
                                $mgr['first_name'] . ' ' . $mgr['last_name']
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- STATUS -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Status

                </label>

                <select
                    name="status"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                >

                    <option
                        value="planning"
                        <?= old(
                            'status',
                            $project['status']
                        ) === 'planning'
                            ? 'selected'
                            : '' ?>
                    >
                        Planning
                    </option>

                    <option
                        value="active"
                        <?= old(
                            'status',
                            $project['status']
                        ) === 'active'
                            ? 'selected'
                            : '' ?>
                    >
                        Active
                    </option>

                    <option
                        value="on_hold"
                        <?= old(
                            'status',
                            $project['status']
                        ) === 'on_hold'
                            ? 'selected'
                            : '' ?>
                    >
                        On Hold
                    </option>

                </select>

            </div>


            <!-- PRIORITY -->

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-1">

                    Priority

                </label>

                <select
                    name="priority"
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                >

                    <option
                        value="low"
                        <?= old(
                            'priority',
                            $project['priority']
                        ) === 'low'
                            ? 'selected'
                            : '' ?>
                    >
                        Low
                    </option>

                    <option
                        value="medium"
                        <?= old(
                            'priority',
                            $project['priority']
                        ) === 'medium'
                            ? 'selected'
                            : '' ?>
                    >
                        Medium
                    </option>

                    <option
                        value="high"
                        <?= old(
                            'priority',
                            $project['priority']
                        ) === 'high'
                            ? 'selected'
                            : '' ?>
                    >
                        High
                    </option>

                    <option
                        value="critical"
                        <?= old(
                            'priority',
                            $project['priority']
                        ) === 'critical'
                            ? 'selected'
                            : '' ?>
                    >
                        Critical
                    </option>

                </select>

            </div>

        </div>


        <!-- DESCRIPTION -->

        <div>

            <label class="block text-sm font-medium text-gray-700 mb-1">

                Description

            </label>

            <textarea
                name="description"
                rows="3"
                class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
            ><?= old('description', $project['description']) ?></textarea>

        </div>


        <!-- BUTTONS -->

        <div class="pt-4">

            <button
                type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition"
            >

                Update Project

            </button>

            <a
                href="<?= url('modules/projects/index.php') ?>"
                class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900"
            >

                Cancel

            </a>

        </div>

    </form>

</div>


<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>


<script>

$(document).ready(function () {


    /*
    |--------------------------------------------------------------------------
    | PROJECT NAME
    |--------------------------------------------------------------------------
    */

    $("#editProjectForm input[name='project_name']").on("input", function () {

        this.value = this.value
            .replace(/[^A-Za-z0-9 .,&()_/-]/g, "")
            .slice(0, 200);

    });


    /*
    |--------------------------------------------------------------------------
    | CLIENT NAME
    |--------------------------------------------------------------------------
    */

    $("#editProjectForm input[name='client_name']").on("input", function () {

        this.value = this.value
            .replace(/[^A-Za-z0-9 .,&()_/-]/g, "")
            .slice(0, 200);

    });


    /*
    |--------------------------------------------------------------------------
    | DESCRIPTION
    |--------------------------------------------------------------------------
    */

    $("#editProjectForm textarea[name='description']").on("input", function () {

        this.value = this.value.slice(0, 500);

    });


    /*
    |--------------------------------------------------------------------------
    | ESTIMATED HOURS
    |--------------------------------------------------------------------------
    */

    // $("#editProjectForm input[name='estimated_hours']").on("input", function () {

    //     this.value = this.value.replace(/[^0-9.]/g, "");

    //     var parts = this.value.split(".");

    //     if (parts.length > 2) {

    //         this.value =
    //             parts[0] + "." + parts.slice(1).join("");

    //     }

    // });


    /*
    |--------------------------------------------------------------------------
    | DATE INPUTS
    |--------------------------------------------------------------------------
    */

    var startDateInput =
        $("#editProjectForm input[name='start_date']");

    var endDateInput =
        $("#editProjectForm input[name='end_date']");


    /*
    |--------------------------------------------------------------------------
    | GET TODAY
    |--------------------------------------------------------------------------
    */

    function getTodayDate() {

        var today = new Date();

        var year = today.getFullYear();

        var month = String(
            today.getMonth() + 1
        ).padStart(2, "0");

        var day = String(
            today.getDate()
        ).padStart(2, "0");

        return year + "-" + month + "-" + day;
    }


    var today = getTodayDate();


    /*
    |--------------------------------------------------------------------------
    | EXISTING START DATE
    |--------------------------------------------------------------------------
    */

    var existingStartDate =
        startDateInput.val();


    /*
    |--------------------------------------------------------------------------
    | START DATE MIN
    |--------------------------------------------------------------------------
    |
    | If existing Start Date is past:
    | keep existing date available.
    |
    | Otherwise:
    | Today is minimum.
    |
    */

    if (
        existingStartDate &&
        existingStartDate < today
    ) {

        startDateInput.attr(
            "min",
            existingStartDate
        );

    } else {

        startDateInput.attr(
            "min",
            today
        );

    }


    /*
    |--------------------------------------------------------------------------
    | END DATE MIN
    |--------------------------------------------------------------------------
    */

    if (existingStartDate) {

        endDateInput.attr(
            "min",
            existingStartDate
        );

    } else {

        endDateInput.attr(
            "min",
            today
        );

    }


    /*
    |--------------------------------------------------------------------------
    | START DATE CHANGE
    |--------------------------------------------------------------------------
    */

    startDateInput.on("change", function () {

        var startDate = $(this).val();


        if (!startDate) {

            endDateInput.attr(
                "min",
                today
            );

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | END DATE MIN = START DATE
        |--------------------------------------------------------------------------
        */

        endDateInput.attr(
            "min",
            startDate
        );


        /*
        |--------------------------------------------------------------------------
        | CHECK EXISTING END DATE
        |--------------------------------------------------------------------------
        */

        var endDate =
            endDateInput.val();


        if (
            endDate &&
            endDate < startDate
        ) {

            endDateInput.val("");

        }


        /*
        |--------------------------------------------------------------------------
        | REVALIDATE
        |--------------------------------------------------------------------------
        */

        endDateInput.valid();

    });


    /*
    |--------------------------------------------------------------------------
    | CUSTOM DATE VALIDATION
    |--------------------------------------------------------------------------
    */

    $.validator.addMethod(
        "validProjectDateRange",
        function (value, element) {

            var startDate =
                startDateInput.val();


            if (
                !startDate ||
                !value
            ) {

                return true;

            }


            return value >= startDate;

        },
        "End Date cannot be before Start Date"
    );


    /*
    |--------------------------------------------------------------------------
    | JQUERY VALIDATION
    |--------------------------------------------------------------------------
    */

    $("#editProjectForm").validate({

        rules: {

            project_name: {

                required: true,

                minlength: 3,

                maxlength: 200

            },


            client_name: {

                maxlength: 200

            },


            start_date: {

                required: true

            },


            end_date: {

                required: true,

                validProjectDateRange: true

            },


            estimated_hours: {

                min: 0

            },


            project_manager_id: {

                required: false

            },


            status: {

                required: true

            },


            priority: {

                required: true

            },


            description: {

                maxlength: 500

            }

        },


        messages: {

            project_name: {

                required:
                    "Project Name is required",

                minlength:
                    "Project Name must be at least 3 characters",

                maxlength:
                    "Project Name must not exceed 200 characters"

            },


            client_name: {

                maxlength:
                    "Client Name must not exceed 200 characters"

            },


            start_date: {

                required:
                    "Start Date is required"

            },


            end_date: {

                required:
                    "End Date is required",

                validProjectDateRange:
                    "End Date cannot be before Start Date"

            },


            estimated_hours: {

                number:
                    "Estimated Hours must be a valid number",

                min:
                    "Estimated Hours cannot be negative"

            },


            project_manager_id: {

                required:
                    "Please select a project manager"

            },


            status: {

                required:
                    "Please select project status"

            },


            priority: {

                required:
                    "Please select project priority"

            },


            description: {

                maxlength:
                    "Description must not exceed 500 characters"

            }

        },


        errorElement: "span",


        errorClass:
            "text-red-500 text-sm mt-1 block",


        errorPlacement: function (
            error,
            element
        ) {

            if (element.is("select")) {

                element
                    .closest("div")
                    .append(error);

            } else {

                error.insertAfter(element);

            }

        },


        highlight: function (element) {

            $(element)
                .addClass("border-red-500");

        },


        unhighlight: function (element) {

            $(element)
                .removeClass("border-red-500");

        }

    });

});

</script>


<?php require_once '../../includes/footer.php'; ?>