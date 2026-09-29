<?php
$pageTitle = 'Support Tickets';
require_once '../../includes/header.php';
require_once '../../helpers/redirect.php';
require_once '../../helpers/pagination.php';

if (!hasPermission('tickets.view')) { die('Access Denied'); }

$db = getDB();
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';
$category = $_GET['category'] ?? '';
$assignedTo = $_GET['assigned_to'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$page = getPageNumber();
$perPage = 15;

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (t.ticket_number LIKE ? OR t.subject LIKE ? OR t.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status) { $where .= " AND t.status = ?"; $params[] = $status; }
if ($priority) { $where .= " AND t.priority = ?"; $params[] = $priority; }
if ($category) { $where .= " AND t.category_id = ?"; $params[] = $category; }
if ($assignedTo) { $where .= " AND t.assigned_to = ?"; $params[] = $assignedTo; }
if ($dateFrom) { $where .= " AND t.created_at >= ?"; $params[] = $dateFrom . ' 00:00:00'; }
if ($dateTo) { $where .= " AND t.created_at <= ?"; $params[] = $dateTo . ' 23:59:59'; }

$countStmt = $db->prepare("SELECT COUNT(*) as cnt FROM tickets t $where");
$countStmt->execute($params);
$total = $countStmt->fetch()['cnt'];
$pagination = paginate($total, $perPage, $page);

$queryParams = array_merge($params, [$perPage, $pagination['offset']]);
$stmt = $db->prepare("
    SELECT t.*, tc.category_name, tc.category_code,
           e.first_name, e.last_name,
           cl.company_name as client_name,
           sla.sla_name
    FROM tickets t
    LEFT JOIN ticket_categories tc ON t.category_id = tc.id
    LEFT JOIN employees e ON t.assigned_to = e.id
    LEFT JOIN clients cl ON t.client_id = cl.id
    LEFT JOIN slas sla ON t.sla_id = sla.id
    $where ORDER BY t.created_at DESC LIMIT ? OFFSET ?
");
$stmt->execute($queryParams);
$tickets = $stmt->fetchAll();

$categories = $db->query("SELECT id, category_name FROM ticket_categories WHERE status = 'active' ORDER BY category_name")->fetchAll();
$employees = $db->query("SELECT id, first_name, last_name FROM employees WHERE status = 'active' ORDER BY first_name")->fetchAll();

$openCount = $db->query("SELECT COUNT(*) as c FROM tickets WHERE status = 'open'")->fetch()['c'];
$inProgressCount = $db->query("SELECT COUNT(*) as c FROM tickets WHERE status = 'in_progress'")->fetch()['c'];
$resolvedCount = $db->query("SELECT COUNT(*) as c FROM tickets WHERE status = 'resolved'")->fetch()['c'];
$slaBreachCount = $db->query("SELECT COUNT(*) as c FROM tickets WHERE sla_breached = 1 AND status NOT IN ('resolved', 'closed')")->fetch()['c'];
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Support Tickets</h1>
        <p class="text-gray-600">Manage and track support tickets</p>
    </div>
    <?php if (hasPermission('tickets.create')): ?>
    <a href="<?= url('modules/tickets/create.php') ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"><i class="fas fa-plus mr-2"></i>New Ticket</a>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
        <div class="text-sm text-gray-500">Open</div>
        <div class="text-2xl font-bold text-blue-600"><?= $openCount ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-yellow-500">
        <div class="text-sm text-gray-500">In Progress</div>
        <div class="text-2xl font-bold text-yellow-600"><?= $inProgressCount ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
        <div class="text-sm text-gray-500">Resolved</div>
        <div class="text-2xl font-bold text-green-600"><?= $resolvedCount ?></div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500">
        <div class="text-sm text-gray-500">SLA Breached</div>
        <div class="text-2xl font-bold text-red-600"><?= $slaBreachCount ?></div>
    </div>
</div>

<div class="bg-white rounded-lg shadow mb-6">
    <form method="GET" class="p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Ticket #, subject..." class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">All</option>
                    <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Open</option>
                    <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="waiting_client" <?= $status === 'waiting_client' ? 'selected' : '' ?>>Waiting Client</option>
                    <option value="resolved" <?= $status === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                    <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Closed</option>
                    <option value="reopened" <?= $status === 'reopened' ? 'selected' : '' ?>>Reopened</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                <select name="priority" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">All</option>
                    <option value="low" <?= $priority === 'low' ? 'selected' : '' ?>>Low</option>
                    <option value="medium" <?= $priority === 'medium' ? 'selected' : '' ?>>Medium</option>
                    <option value="high" <?= $priority === 'high' ? 'selected' : '' ?>>High</option>
                    <option value="critical" <?= $priority === 'critical' ? 'selected' : '' ?>>Critical</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select name="category" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">All</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>><?= sanitize($cat['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Assigned To</label>
                <select name="assigned_to" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
                    <option value="">All</option>
                    <?php foreach ($employees as $emp): ?>
                    <option value="<?= $emp['id'] ?>" <?= $assignedTo == $emp['id'] ? 'selected' : '' ?>><?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                <input type="date" id="date_from" name="date_from" value="<?= sanitize($dateFrom) ?>" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                <input type="date" id="date_to" name="date_to" value="<?= sanitize($dateTo) ?>" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="flex items-end">
                <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"><i class="fas fa-search mr-1"></i>Filter</button>
                <a href="<?= url('modules/tickets/index.php') ?>" class="ml-2 text-gray-600 border border-gray-200 hover:text-gray-900 px-3 py-2">Clear</a>
            </div>
        </div>
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ticket</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Priority</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Assigned</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Due</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($tickets as $ticket): ?>
            <tr class="hover:bg-gray-50 <?= $ticket['sla_breached'] ? 'bg-red-50' : '' ?>">
                <td class="px-4 py-4">
                    <span class="font-mono text-sm font-medium text-blue-600"><?= sanitize($ticket['ticket_number']) ?></span>
                </td>
                <td class="px-4 py-4">
                    <div class="font-medium text-gray-900 max-w-xs truncate"><?= sanitize($ticket['subject']) ?></div>
                    <div class="text-xs text-gray-400"><?= date('M d, Y H:i', strtotime($ticket['created_at'])) ?></div>
                </td>
                <td class="px-4 py-4 text-sm text-gray-600"><?= sanitize($ticket['client_name'] ?? '-') ?></td>
                <td class="px-4 py-4">
                    <span class="text-xs bg-gray-100 px-2 py-1 rounded"><?= sanitize($ticket['category_code'] ?? '-') ?></span>
                </td>
                <td class="px-4 py-4">
                    <?php
                    $priorityColors = ['low' => 'bg-gray-100 text-gray-700', 'medium' => 'bg-blue-100 text-blue-800', 'high' => 'bg-orange-100 text-orange-800', 'critical' => 'bg-red-100 text-red-800'];
                    $pClass = $priorityColors[$ticket['priority']] ?? 'bg-gray-100 text-gray-700';
                    ?>
                    <span class="px-2 py-1 text-xs rounded font-medium <?= $pClass ?>"><?= ucfirst($ticket['priority']) ?></span>
                </td>
                <td class="px-4 py-4">
                    <?php
                    $statusColors = ['open' => 'bg-blue-100 text-blue-800', 'in_progress' => 'bg-yellow-100 text-yellow-800', 'waiting_client' => 'bg-purple-100 text-purple-800', 'resolved' => 'bg-green-100 text-green-800', 'closed' => 'bg-gray-100 text-gray-800', 'reopened' => 'bg-red-100 text-red-800'];
                    $sClass = $statusColors[$ticket['status']] ?? 'bg-gray-100 text-gray-700';
                    ?>
                    <span class="px-2 py-1 text-xs rounded font-medium <?= $sClass ?>"><?= str_replace('_', ' ', ucfirst($ticket['status'])) ?></span>
                    <?php if ($ticket['sla_breached']): ?>
                    <span class="ml-1 px-1 py-0.5 text-xs rounded bg-red-600 text-white" title="SLA Breached"><i class="fas fa-exclamation-triangle"></i></span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-4 text-sm text-gray-600"><?= sanitize(($ticket['first_name'] ?? '') . ' ' . ($ticket['last_name'] ?? '')) ?: '<span class="text-gray-400">Unassigned</span>' ?></td>
                <td class="px-4 py-4 text-sm">
                    <?php if ($ticket['due_date']): ?>
                        <?php
                        $due = new DateTime($ticket['due_date']);
                        $now = new DateTime();
                        $overdue = $due < $now && !in_array($ticket['status'], ['resolved', 'closed']);
                        ?>
                        <span class="<?= $overdue ? 'text-red-600 font-semibold' : 'text-gray-600' ?>"><?= $due->format('M d') ?></span>
                    <?php else: ?>
                        <span class="text-gray-400">-</span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-4 text-right">
                    <a href="<?= url('modules/tickets/view.php?id=' . $ticket['id'] . '') ?>" class="text-blue-600 hover:text-blue-900 mr-2" title="View"><i class="fas fa-eye"></i></a>
                    <?php if (hasPermission('tickets.edit')): ?>
                    <a href="<?= url('modules/tickets/edit.php?id=' . $ticket['id'] . '') ?>" class="text-yellow-600 hover:text-yellow-900 mr-2" title="Edit"><i class="fas fa-edit"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($tickets)): ?>
            <tr><td colspan="9" class="px-6 py-8 text-center text-gray-500">No tickets found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?= paginationLinks($pagination, '?' . http_build_query(array_filter(['search' => $search, 'status' => $status, 'priority' => $priority, 'category' => $category, 'assigned_to' => $assignedTo, 'date_from' => $dateFrom, 'date_to' => $dateTo]))) ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>

<script>

$(document).ready(function () {

    /*
    |--------------------------------------------------------------------------
    | FROM DATE
    |--------------------------------------------------------------------------
    | From date can be any date.
    |--------------------------------------------------------------------------
    */


    /*
    |--------------------------------------------------------------------------
    | TO DATE
    |--------------------------------------------------------------------------
    | Initially no restriction.
    |--------------------------------------------------------------------------
    */


    /*
    |--------------------------------------------------------------------------
    | FROM DATE CHANGE
    |--------------------------------------------------------------------------
    */

    $('#date_from').on('change', function () {

        const fromDate = $(this).val();

        const toDate = $('#date_to').val();


        if (fromDate) {

            /*
             * To Date cannot be before From Date
             */
            $('#date_to').attr('min', fromDate);


            /*
             * If already selected To Date
             * is before From Date, clear it.
             */
            if (toDate && toDate < fromDate) {

                $('#date_to').val('');

            }

        } else {

            /*
             * Remove restriction
             */
            $('#date_to').removeAttr('min');

        }


        /*
         * Revalidate To Date
         */
        $('#date_to').valid();

    });


    /*
    |--------------------------------------------------------------------------
    | CUSTOM VALIDATION
    |--------------------------------------------------------------------------
    */

    $.validator.addMethod(
        'validDateRange',
        function (value, element) {

            /*
             * To Date is optional
             */
            if (!value) {
                return true;
            }


            const fromDate = $('#date_from').val();


            /*
             * If From Date is empty,
             * no comparison required.
             */
            if (!fromDate) {
                return true;
            }


            /*
             * To Date must be same or after From Date
             */
            return value >= fromDate;

        },
        'To Date must be on or after From Date.'
    );


    /*
    |--------------------------------------------------------------------------
    | FORM VALIDATION
    |--------------------------------------------------------------------------
    */

    $('#costAuditFilterForm').validate({

        rules: {

            date_from: {
                date: true
            },

            date_to: {
                date: true,
                validDateRange: true
            }

        },

        messages: {

            date_from: {
                date: 'Please enter a valid From Date.'
            },

            date_to: {
                date: 'Please enter a valid To Date.',
                validDateRange:
                    'To Date must be on or after From Date.'
            }

        },

        errorElement: 'span',

        errorClass: 'error text-red-600 text-xs mt-1 block',

        errorPlacement: function (error, element) {

            error.insertAfter(element);

        },

        highlight: function (element) {

            $(element)
                .addClass('border-red-500')
                .removeClass('border-gray-300');

        },

        unhighlight: function (element) {

            $(element)
                .removeClass('border-red-500')
                .addClass('border-gray-300');

        }

    });


    /*
    |--------------------------------------------------------------------------
    | SET TO DATE MIN ON PAGE LOAD
    |--------------------------------------------------------------------------
    | Important when page loads with existing GET filters.
    |--------------------------------------------------------------------------
    */

    const existingFromDate = $('#date_from').val();

    if (existingFromDate) {

        $('#date_to').attr(
            'min',
            existingFromDate
        );

    }

});

</script>


<?php require_once '../../includes/footer.php'; ?>


