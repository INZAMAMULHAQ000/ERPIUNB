<?php
$pageTitle = 'Holidays';

require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';

if (!hasPermission('holidays.view')) {
    die('Access Denied');
}

$db = getDB();

$year = $_GET['year'] ?? date('Y');

/*
|--------------------------------------------------------------------------
| Validate Year
|--------------------------------------------------------------------------
*/
if (!preg_match('/^\d{4}$/', (string)$year)) {
    $year = date('Y');
}

$year = (int)$year;

/*
|--------------------------------------------------------------------------
| Holidays by selected year
|--------------------------------------------------------------------------
*/
$holidays = $db->prepare("
    SELECT *
    FROM holidays
    WHERE YEAR(holiday_date) = ?
    ORDER BY holiday_date
");

$holidays->execute([$year]);

$holidayList = $holidays->fetchAll();

/*
|--------------------------------------------------------------------------
| Upcoming Holidays
|--------------------------------------------------------------------------
*/
$upcoming = $db->query("
    SELECT *
    FROM holidays
    WHERE holiday_date >= CURDATE()
    ORDER BY holiday_date
    LIMIT 5
")->fetchAll();

/*
|--------------------------------------------------------------------------
| Holiday Type Colors
|--------------------------------------------------------------------------
*/
$typeColors = [
    'national' => 'bg-red-100 text-red-800',
    'festival' => 'bg-purple-100 text-purple-800',
    'optional' => 'bg-blue-100 text-blue-800'
];
?>

<style>
    /*
    |--------------------------------------------------------------------------
    | Custom Select
    |--------------------------------------------------------------------------
    */
    #holidayFilter select {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-image: none !important;
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Modal
    |--------------------------------------------------------------------------
    */
    #deleteHolidayModal {
        display: none;
    }

    #deleteHolidayModal.show {
        display: flex;
    }

    body.modal-open {
        overflow: hidden;
    }
</style>


<!-- =========================================================
     PAGE HEADER
========================================================= -->
<div class="mb-6 flex justify-between items-center">

    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            Holiday Calendar
        </h1>

        <p class="text-gray-600">
            Manage company holidays
        </p>
    </div>

    <?php if (hasPermission('holidays.create')): ?>

        <a
            href="<?= url('modules/holidays/create.php') ?>"
            class="bg-blue-600 text-white px-4 py-2 rounded
                   hover:bg-blue-700 transition">
            <i class="fas fa-plus mr-2"></i>
            Add Holiday
        </a>

    <?php endif; ?>

</div>


<!-- =========================================================
     UPCOMING HOLIDAYS
========================================================= -->
<?php if ($upcoming): ?>

    <div class="bg-white rounded-lg shadow p-6 mb-6">

        <h2 class="text-lg font-semibold text-gray-800 mb-4">
            Upcoming Holidays
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">

            <?php foreach ($upcoming as $h): ?>

                <div
                    class="bg-gray-50
                           border border-gray-200
                           p-3 rounded">

                    <div class="font-medium text-gray-800">
                        <?= sanitize($h['holiday_name']) ?>
                    </div>

                    <div class="text-sm text-gray-500 mt-1">
                        <?= date(
                            'M d, Y',
                            strtotime($h['holiday_date'])
                        ) ?>
                    </div>

                    <div class="mt-2">

                        <span
                            class="text-xs px-2 py-0.5 rounded
                                   <?= $typeColors[$h['holiday_type']]
                                        ?? 'bg-gray-100 text-gray-800' ?>">
                            <?= ucfirst(
                                sanitize($h['holiday_type'])
                            ) ?>
                        </span>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    </div>

<?php endif; ?>


<!-- =========================================================
     YEAR FILTER
========================================================= -->
<div class="bg-white rounded-lg shadow mb-6">

    <form
        method="GET"
        id="holidayFilter"
        class="p-4 flex gap-4 items-end">

        <div class="w-36">

            <label
                for="year"
                class="block text-sm font-medium
                       text-gray-700 mb-1">
                Year
            </label>

            <div class="relative">

                <select
                    id="year"
                    name="year"
                    class="w-full px-3 py-2 pr-9
                           border border-gray-300
                           rounded bg-white
                           focus:outline-none
                           focus:ring-2
                           focus:ring-blue-500">

                    <?php
                    $currentYear = (int)date('Y');

                    for (
                        $y = $currentYear - 1;
                        $y <= $currentYear + 1;
                        $y++
                    ):
                    ?>

                        <option
                            value="<?= $y ?>"
                            <?= $year == $y ? 'selected' : '' ?>>
                            <?= $y ?>
                        </option>

                    <?php endfor; ?>

                </select>

                <span
                    class="pointer-events-none
                           absolute right-3 top-1/2
                           -translate-y-1/2
                           text-gray-500">
                    <i class="fas fa-chevron-down text-xs"></i>
                </span>

            </div>

        </div>


        <button
            type="submit"
            class="bg-gray-600 text-white
                   px-4 py-2 rounded
                   hover:bg-gray-700 transition">
            <i class="fas fa-filter mr-1"></i>
            Filter
        </button>

    </form>

</div>


<!-- =========================================================
     HOLIDAYS TABLE
========================================================= -->
<div class="bg-white rounded-lg shadow overflow-hidden">

    <div class="overflow-x-auto">

        <table class="w-full">

            <thead class="bg-gray-50">

                <tr>

                    <th
                        class="px-6 py-3 text-left
                               text-xs font-medium
                               text-gray-500 uppercase">
                        Date
                    </th>

                    <th
                        class="px-6 py-3 text-left
                               text-xs font-medium
                               text-gray-500 uppercase">
                        Holiday Name
                    </th>

                    <th
                        class="px-6 py-3 text-left
                               text-xs font-medium
                               text-gray-500 uppercase">
                        Type
                    </th>

                    <th
                        class="px-6 py-3 text-left
                               text-xs font-medium
                               text-gray-500 uppercase">
                        Description
                    </th>

                    <th
                        class="px-6 py-3 text-right
                               text-xs font-medium
                               text-gray-500 uppercase">
                        Actions
                    </th>

                </tr>

            </thead>


            <tbody class="divide-y divide-gray-200">

                <?php foreach ($holidayList as $holiday): ?>

                    <tr class="hover:bg-gray-50">

                        <!-- Date -->
                        <td class="px-6 py-4 text-gray-600">

                            <?= date(
                                'M d, Y',
                                strtotime($holiday['holiday_date'])
                            ) ?>

                        </td>


                        <!-- Holiday Name -->
                        <td
                            class="px-6 py-4
                                   font-medium
                                   text-gray-900">

                            <?= sanitize(
                                $holiday['holiday_name']
                            ) ?>

                        </td>


                        <!-- Type -->
                        <td class="px-6 py-4">

                            <span
                                class="px-2 py-1
                                       text-xs rounded
                                       <?= $typeColors[$holiday['holiday_type']]
                                            ?? 'bg-gray-100 text-gray-800' ?>">
                                <?= ucfirst(
                                    sanitize(
                                        $holiday['holiday_type']
                                    )
                                ) ?>
                            </span>

                        </td>


                        <!-- Description -->
                        <td class="px-6 py-4 text-gray-600">

                            <?= sanitize(
                                $holiday['description'] ?? '-'
                            ) ?>

                        </td>


                        <!-- Actions -->
                        <td class="px-6 py-4 text-right">

                            <?php if (
                                hasPermission('holidays.view')
                            ): ?>

                                <a
                                    href="view.php?id=<?= (int)$holiday['id'] ?>"
                                    class="text-blue-600
                                           hover:text-blue-900
                                           mr-2"
                                    title="View">
                                    <i class="fas fa-eye"></i>
                                </a>

                            <?php endif; ?>


                            <?php if (
                                hasPermission('holidays.edit')
                            ): ?>

                                <a
                                    href="<?= url(
                                                'modules/holidays/edit.php?id='
                                                    . (int)$holiday['id']
                                            ) ?>"
                                    class="text-yellow-600
                                           hover:text-yellow-900
                                           mr-2"
                                    title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>

                            <?php endif; ?>


                            <?php if (
                                hasPermission('holidays.delete')
                            ): ?>

                                <button
                                    type="button"
                                    class="text-red-600
                                           hover:text-red-900"
                                    title="Delete"
                                    onclick="openDeleteHolidayModal(
                                        <?= (int)$holiday['id'] ?>,
                                        <?= htmlspecialchars(
                                            json_encode(
                                                $holiday['holiday_name'],
                                                JSON_HEX_TAG |
                                                    JSON_HEX_APOS |
                                                    JSON_HEX_QUOT |
                                                    JSON_HEX_AMP
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    )">
                                    <i class="fas fa-trash"></i>
                                </button>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>


                <?php if (empty($holidayList)): ?>

                    <tr>

                        <td
                            colspan="5"
                            class="px-6 py-8
                                   text-center
                                   text-gray-500">
                            No holidays found for <?= $year ?>
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<!-- =========================================================
     DELETE HOLIDAY MODAL
========================================================= -->
<div
    id="deleteHolidayModal"
    class="fixed inset-0 z-50
           items-center justify-center
           bg-black/50 px-4">

    <div
        class="bg-white w-full max-w-md shadow-xl"
        onclick="event.stopPropagation()">

        <!-- Header -->
        <div
            class="flex items-center
                   justify-between
                   px-5 py-4
                   border-b border-gray-200">

            <div class="flex items-center gap-3">

                <div
                    class="w-10 h-10
                           flex items-center
                           justify-center
                           bg-red-100
                           text-red-600">
                    <i class="fas fa-trash-alt"></i>
                </div>

                <div>

                    <h3
                        class="text-base
                               font-semibold
                               text-gray-800">
                        Delete Holiday
                    </h3>

                    <p class="text-xs text-gray-500">
                        Confirmation required
                    </p>

                </div>

            </div>


            <button
                type="button"
                onclick="closeDeleteHolidayModal()"
                class="text-gray-400
                       hover:text-gray-600"
                title="Close">
                <i class="fas fa-times"></i>
            </button>

        </div>


        <!-- Body -->
        <div class="px-5 py-5">

            <p
                class="text-sm
                       text-gray-700
                       leading-6">

                Are you sure you want to delete

                <span
                    id="deleteHolidayName"
                    class="font-semibold
                           text-gray-900"></span>

                ?

            </p>


            <div
                class="mt-4 px-3 py-3
                       bg-yellow-50
                       border border-yellow-200
                       text-yellow-800
                       text-xs">

                <i
                    class="fas fa-exclamation-triangle mr-1"></i>

                This action cannot be undone.

            </div>

        </div>


        <!-- Footer -->
        <div
            class="flex justify-end gap-3
                   px-5 py-4
                   border-t border-gray-200
                   bg-gray-50">

            <button
                type="button"
                onclick="closeDeleteHolidayModal()"
                class="px-4 py-2
                       text-sm
                       border border-gray-300
                       bg-white
                       text-gray-700
                       rounded
                       hover:bg-gray-100">
                Cancel
            </button>


            <a
                href="#"
                id="confirmDeleteHoliday"
                class="px-4 py-2
                       text-sm
                       bg-red-600
                       text-white
                       rounded
                       hover:bg-red-700">
                <i class="fas fa-trash mr-1"></i>
                Delete
            </a>

        </div>

    </div>

</div>


<script>
    (function() {

        const modal = document.getElementById(
            'deleteHolidayModal'
        );

        const nameElement = document.getElementById(
            'deleteHolidayName'
        );

        const confirmButton = document.getElementById(
            'confirmDeleteHoliday'
        );


        /*
        |--------------------------------------------------------------------------
        | Open Delete Modal
        |--------------------------------------------------------------------------
        */
        window.openDeleteHolidayModal = function(
            holidayId,
            holidayName
        ) {

            nameElement.textContent = holidayName;

            /*
             * Existing delete.php logic is preserved.
             */
            confirmButton.href =
                'delete.php?id=' +
                encodeURIComponent(holidayId);

            modal.classList.add('show');

            document.body.classList.add('modal-open');
        };


        /*
        |--------------------------------------------------------------------------
        | Close Delete Modal
        |--------------------------------------------------------------------------
        */
        window.closeDeleteHolidayModal = function() {

            modal.classList.remove('show');

            document.body.classList.remove('modal-open');

            nameElement.textContent = '';

            confirmButton.href = '#';
        };


        /*
        |--------------------------------------------------------------------------
        | Click outside
        |--------------------------------------------------------------------------
        */
        modal.addEventListener('click', function(event) {

            if (event.target === modal) {
                closeDeleteHolidayModal();
            }

        });


        /*
        |--------------------------------------------------------------------------
        | ESC key
        |--------------------------------------------------------------------------
        */
        document.addEventListener('keydown', function(event) {

            if (
                event.key === 'Escape' &&
                modal.classList.contains('show')
            ) {
                closeDeleteHolidayModal();
            }

        });

    })();
</script>


<?php require_once '../../includes/footer.php'; ?>