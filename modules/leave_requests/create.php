<?php
$pageTitle = 'New Leave Request';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/validation.php';

if (!hasPermission('leaves.create')) {
    die('Access Denied');
}

$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

$employees = $db->query("SELECT id, first_name, last_name, employee_code FROM employees WHERE status = 'active' ORDER BY first_name")->fetchAll();
$leaveTypes = $db->query("SELECT * FROM leave_types WHERE status = 'active' ORDER BY leave_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'employee_id' => $_POST['employee_id'] ?? '',
        'leave_type_id' => $_POST['leave_type_id'] ?? '',
        'from_date' => $_POST['from_date'] ?? '',
        'to_date' => $_POST['to_date'] ?? '',
        'half_day' => !empty($_POST['half_day']) ? 1 : 0,
        'reason' => $_POST['reason'] ?? ''
    ];

    $errors = validate($data, [
        'employee_id' => 'required',
        'leave_type_id' => 'required',
        'from_date' => 'required',
        'to_date' => 'required',
        'reason' => 'required'
    ]);

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/leave_requests/create.php');
    }

    $from = strtotime($data['from_date']);
    $to = strtotime($data['to_date']);
    $days = ceil(($to - $from) / (60 * 60 * 24)) + 1;

    if ($data['half_day']) {
        $days = 0.5;
    }

    // ——— Balance Check & LOP Detection ———
    $isLop = 0;
    $balanceInfo = '';

    // Get available balance for this leave type in current year
    $year = date('Y');
    $balStmt = $db->prepare("
        SELECT COALESCE(lb.total_days, lt.total_days) as allocated,
               COALESCE(lb.used_days, 0) as used
        FROM leave_types lt
        LEFT JOIN leave_balances lb ON lb.leave_type_id = lt.id
            AND lb.employee_id = ? AND lb.year = ?
        WHERE lt.id = ?
    ");
    $balStmt->execute([$data['employee_id'], $year, $data['leave_type_id']]);
    $balance = $balStmt->fetch();

    if ($balance) {
        $remaining = $balance['allocated'] - $balance['used'];

        // Check if LOP also considers pending leaves that might be approved
        $pendingStmt = $db->prepare("
            SELECT COALESCE(SUM(total_days), 0) as pending_days
            FROM leave_requests
            WHERE employee_id = ? AND leave_type_id = ? AND status = 'pending'
            AND YEAR(from_date) = ?
        ");
        $pendingStmt->execute([$data['employee_id'], $data['leave_type_id'], $year]);
        $pendingDays = $pendingStmt->fetch()['pending_days'];

        $effectiveRemaining = $remaining - $pendingDays;

        if ($days > $effectiveRemaining && $effectiveRemaining > 0) {
            // Partial LOP — some days covered, rest is LOP
            $isLop = 1;
            $lopDays = $days - $effectiveRemaining;
            $balanceInfo = "Warning: Only {$effectiveRemaining} day(s) remaining. {$lopDays} day(s) will be Loss of Pay (LOP).";
        } elseif ($effectiveRemaining <= 0) {
            // Full LOP
            $isLop = 1;
            $balanceInfo = "No leave balance remaining. This will be marked as Loss of Pay (LOP).";
        }
    }

    $stmt = $db->prepare("INSERT INTO leave_requests (employee_id, leave_type_id, from_date, to_date, total_days, is_lop, half_day, reason, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$data['employee_id'], $data['leave_type_id'], $data['from_date'], $data['to_date'], $days, $isLop, $data['half_day'], $data['reason']]);

    logAudit($_SESSION['user_id'], 'create_leave_request', 'leaves');

    if ($isLop) {
        flash('info', 'Leave request submitted. Note: Some/all days will be Loss of Pay (LOP) due to insufficient balance.');
    } else {
        flash('success', 'Leave request submitted successfully');
    }

    to('modules/leave_requests/index.php');
}
?>

<div class="mb-6">
    <a href="<?= url('modules/leave_management/dashboard.php') ?>" class="text-blue-600 hover:text-blue-900">
        <i class="fas fa-arrow-left mr-1"></i>Back to Leave Dashboard
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-6">New Leave Request</h2>

    <?php if ($errors): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $error): ?>
                    <li><?= sanitize($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" id="leaveRequestForm" class="space-y-4" novalidate>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Employee <span class="text-red-600">*</span></labe>
                    <select name="employee_id" id="employee_id" required
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                        onchange="checkBalance()">
                        <option value="">Select Employee</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>" <?= old('employee_id') == $emp['id'] ? 'selected' : '' ?>>
                                <?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?> (<?= $emp['employee_code'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Leave Type <span class="text-red-600">*</span></labe>
                    <select name="leave_type_id" id="leave_type_id" required
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                        onchange="checkBalance()">
                        <option value="">Select Leave Type</option>
                        <?php foreach ($leaveTypes as $lt): ?>
                            <option value="<?= $lt['id'] ?>" <?= old('leave_type_id') == $lt['id'] ? 'selected' : '' ?>>
                                <?= sanitize($lt['leave_name']) ?> (<?= $lt['leave_code'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">From Date <span class="text-red-600">*</span></labe>
                    <input
                        type="date"
                        name="from_date"
                        id="from_date"
                        value="<?= old('from_date') ?>"
                        min="<?= date('Y-m-d') ?>"
                        required
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                        onchange="updateToDateMin(); checkBalance()">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">To Date <span class="text-red-600">*</span></labe>
                    <input
                        type="date"
                        name="to_date"
                        id="to_date"
                        value="<?= old('to_date') ?>"
                        min="<?= date('Y-m-d') ?>"
                        required
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"
                        onchange="checkBalance()">
            </div>
        </div>

        <!-- Half Day checkbox -->
        <div class="flex items-center">
            <input type="checkbox" name="half_day" id="half_day" value="1" <?= old('half_day') ? 'checked' : '' ?>
                class="rounded text-blue-600" onchange="checkBalance()">
            <label for="half_day" class="ml-2 text-sm font-medium text-gray-700">Half Day (0.5 day)</label>
        </div>

        <!-- Balance Info -->
        <div id="balanceInfo" class="hidden p-3 rounded text-sm"></div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Reason <span class="text-red-600">*</span></labe>
                <textarea name="reason" id="reason" rows="3" required
                    class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500"><?= old('reason') ?></textarea>
        </div>

        <div class="pt-4">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                Submit Request
            </button>
            <a href="<?= url('modules/leave_management/dashboard.php') ?>" class="ml-4 text-gray-600 px-6 py-2 border border-gray-200 hover:text-gray-900">Cancel</a>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>

<script>
    function checkBalance() {
        var empId = document.getElementById('employee_id').value;
        var typeId = document.getElementById('leave_type_id').value;
        var fromDate = document.getElementById('from_date').value;
        var toDate = document.getElementById('to_date').value;
        var infoDiv = document.getElementById('balanceInfo');
        var isHalf = document.getElementById('half_day').checked;

        if (!empId || !typeId || !fromDate || !toDate) {
            infoDiv.classList.add('hidden');
            return;
        }

        // Calculate days
        var from = new Date(fromDate);
        var to = new Date(toDate);
        var days = Math.ceil((to - from) / (1000 * 60 * 60 * 24)) + 1;

        if (days < 1) days = 1;

        if (isHalf) days = 0.5;

        // Fetch balance via AJAX
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'balance_check.php?employee_id=' + empId + '&leave_type_id=' + typeId + '&days=' + days, true);

        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);

                    infoDiv.classList.remove(
                        'hidden',
                        'bg-green-50',
                        'bg-yellow-50',
                        'bg-red-50',
                        'text-green-700',
                        'text-yellow-700',
                        'text-red-700',
                        'border',
                        'border-green-200',
                        'border-yellow-200',
                        'border-red-200'
                    );

                    if (data.error) {
                        infoDiv.textContent = data.error;
                        infoDiv.className = 'hidden';
                        return;
                    }

                    if (data.status === 'ok') {
                        infoDiv.className = 'p-3 rounded text-sm bg-green-50 border border-green-200 text-green-700';
                        infoDiv.innerHTML =
                            '<i class="fas fa-check-circle mr-1"></i> Balance available: <strong>' +
                            data.remaining +
                            '</strong> day(s) remaining. Applying <strong>' +
                            days +
                            '</strong> day(s).';

                    } else if (data.status === 'lop_partial') {
                        infoDiv.className = 'p-3 rounded text-sm bg-yellow-50 border border-yellow-200 text-yellow-700';
                        infoDiv.innerHTML =
                            '<i class="fas fa-exclamation-triangle mr-1"></i> <strong>Partial LOP:</strong> Only ' +
                            data.remaining +
                            ' day(s) remaining. ' +
                            data.lop_days +
                            ' day(s) will be Loss of Pay.';

                    } else if (data.status === 'lop_full') {
                        infoDiv.className = 'p-3 rounded text-sm bg-red-50 border border-red-200 text-red-700';
                        infoDiv.innerHTML =
                            '<i class="fas fa-exclamation-circle mr-1"></i> <strong>Loss of Pay:</strong> No balance remaining. All ' +
                            days +
                            ' day(s) will be LOP.';
                    }

                } catch (e) {
                    infoDiv.classList.add('hidden');
                }
            }
        };

        xhr.send();
    }


    // --------------------------------------------------
    // Leave Request - Form Specific Validation
    // --------------------------------------------------

    $(document).ready(function() {

        $("#leaveRequestForm select[name='employee_id']").on("change", function() {
            $(this).valid();
        });

        $("#leaveRequestForm select[name='leave_type_id']").on("change", function() {
            $(this).valid();
        });

        $("#leaveRequestForm input[name='from_date']").on("change", function() {
            $(this).valid();
            $("#leaveRequestForm input[name='to_date']").valid();
        });

        $("#leaveRequestForm input[name='to_date']").on("change", function() {
            $(this).valid();
        });

        // Reason - maximum 500 characters
        $("#leaveRequestForm textarea[name='reason']").on("input", function() {
            this.value = this.value.slice(0, 500);
        });

        // Custom validation: To Date must not be before From Date
        $.validator.addMethod(
            "validLeaveDateRange",
            function(value, element) {
                var fromDate = $("#leaveRequestForm input[name='from_date']").val();

                if (!fromDate || !value) {
                    return true;
                }

                return new Date(value) >= new Date(fromDate);
            },
            "To Date cannot be before From Date"
        );

        $("#leaveRequestForm").validate({

            rules: {
                employee_id: {
                    required: true
                },

                leave_type_id: {
                    required: true
                },

                from_date: {
                    required: true
                },

                to_date: {
                    required: true,
                    validLeaveDateRange: true
                },

                reason: {
                    required: true,
                    minlength: 2,
                    maxlength: 500
                }
            },

            messages: {
                employee_id: {
                    required: "Please select an employee"
                },

                leave_type_id: {
                    required: "Please select a leave type"
                },

                from_date: {
                    required: "From Date is required"
                },

                to_date: {
                    required: "To Date is required",
                    validLeaveDateRange: "To Date cannot be before From Date"
                },

                reason: {
                    required: "Reason is required",
                    minlength: "Reason must be at least 2 characters",
                    maxlength: "Reason must not exceed 500 characters"
                }
            },

            errorElement: "span",

            errorClass: "text-red-500 text-sm mt-1 block",

            // Dropdown error message below dropdown
            errorPlacement: function(error, element) {

                if (element.is("select")) {
                    element.closest("div").append(error);
                } else {
                    error.insertAfter(element);
                }
            },

            highlight: function(element) {
                $(element).addClass("border-red-500");
            },

            unhighlight: function(element) {
                $(element).removeClass("border-red-500");
            }

        });

        // Initial check if values are already set
        if (
            document.getElementById('employee_id').value &&
            document.getElementById('leave_type_id').value
        ) {
            checkBalance();
        }

    });
    
    function updateToDateMin() {
    const fromDate = document.getElementById('from_date').value;
    const toDate = document.getElementById('to_date');

    if (fromDate) {
        toDate.min = fromDate;

        // Already selected To Date is before From Date
        if (toDate.value && toDate.value < fromDate) {
            toDate.value = '';
        }
    } else {
        toDate.min = '<?= date('Y-m-d') ?>';
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>