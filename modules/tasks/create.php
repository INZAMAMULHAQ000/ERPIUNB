<?php
$pageTitle = 'Create Task';
require_once '../../includes/header.php';

if (!hasPermission('tasks.create')) {
    die('Access Denied');
}

$db = getDB();
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}
$project_id = $_GET['project_id'] ?? '';

$projects = $db->query("SELECT id, project_name FROM projects WHERE status IN ('planning', 'active') ORDER BY project_name")->fetchAll();
$milestones = $db->query("SELECT id, milestone_name, project_id FROM milestones WHERE status != 'completed' ORDER BY milestone_name")->fetchAll();
$employees = $db->query("SELECT id, first_name, last_name FROM employees WHERE status = 'active' ORDER BY first_name")->fetchAll();

$statusLabels = ['todo' => 'To Do', 'in_progress' => 'In Progress', 'review' => 'Review', 'testing' => 'Testing', 'completed' => 'Completed', 'blocked' => 'Blocked'];
$priorityLabels = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'project_id'      => $_POST['project_id'] ?? '',
        'milestone_id'    => $_POST['milestone_id'] ?? '',
        'task_title'      => $_POST['task_title'] ?? '',
        'task_description' => $_POST['task_description'] ?? '',
        'start_date'      => $_POST['start_date'] ?? '',
        'due_date'        => $_POST['due_date'] ?? '',
        'estimated_hours' => $_POST['estimated_hours'] ?? '',
        'status'          => $_POST['status'] ?? 'todo',
        'priority'        => $_POST['priority'] ?? 'medium'
    ];
    $assigneeIds = $_POST['assignees'] ?? [];
    $tagNames   = $_POST['tag_names'] ?? [];
    $tagColors  = $_POST['tag_colors'] ?? [];

    $errors = validate($data, [
        'project_id' => 'required',
        'task_title' => 'required|min:3',
        'due_date'   => 'required'
    ]);

    if (!empty($errors)) {
        saveFormState($errors);
        to('modules/tasks/create.php');
    }

    $stmt = $db->prepare("INSERT INTO tasks (project_id, milestone_id, task_title, task_description, start_date, due_date, estimated_hours, status, priority) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['project_id'],
        $data['milestone_id'] ?: null,
        $data['task_title'],
        $data['task_description'],
        $data['start_date'] ?: null,
        $data['due_date'],
        $data['estimated_hours'] ?: null,
        $data['status'],
        $data['priority']
    ]);
    $taskId = $db->lastInsertId();

    // Insert assignees
    if (!empty($assigneeIds)) {
        $ins = $db->prepare("INSERT INTO task_assignees (task_id, employee_id) VALUES (?, ?)");
        foreach ($assigneeIds as $eid) {
            $ins->execute([$taskId, (int)$eid]);
        }
    }

    // Insert tags
    foreach ($tagNames as $i => $name) {
        if (trim($name)) {
            $color = $tagColors[$i] ?? '#6366f1';
            $db->prepare("INSERT INTO task_tags (task_id, tag_name, tag_color) VALUES (?, ?, ?)")
                ->execute([$taskId, trim($name), $color]);
        }
    }

    logAudit($_SESSION['user_id'], 'create_task', 'tasks');
    flash('success', 'Task created successfully');
    to('modules/tasks/index.php');
}
?>

<style>
    .tag-pill-input {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 500;
        color: #fff;
    }

    .tag-pill-input .remove-tag {
        cursor: pointer;
        opacity: 0.7;
        font-size: 0.8rem;
        line-height: 1;
    }

    .tag-pill-input .remove-tag:hover {
        opacity: 1;
    }
</style>

<div class="mb-6 flex items-center justify-between">
    <div>
        <a href="<?= url('modules/tasks/index.php') ?>" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
            <i class="fas fa-arrow-left mr-1"></i> Back to Tasks
        </a>
        <h1 class="text-2xl font-bold text-slate-800 mt-1">Create Task</h1>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-6">
        <ul class="list-disc list-inside text-sm">
            <?php foreach ($errors as $error): ?>
                <li><?= sanitize($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" id="createTaskForm" class="space-y-4" novalidate onsubmit="collectTags()">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- === Left Column (wide) === -->
        <div class="lg:col-span-2 space-y-5">

            <!-- Title -->
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">
                    Task Title <span class="text-red-600">*</span>
                </label>

                <input type="text"
                    name="task_title"
                    value="<?= old('task_title') ?>"
                    required
                    placeholder="Enter task title..."
                    class="w-full px-4 py-2.5 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
            </div>

            <!-- Description -->
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">
                    Description
                </label>

                <textarea name="task_description"
                    rows="4"
                    placeholder="Add a description..."
                    class="w-full px-4 py-2.5 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"><?= old('task_description') ?></textarea>
            </div>

            <!-- Tags / Labels -->
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">
                    Labels
                </h3>

                <div id="tagsPreview" class="flex flex-wrap gap-2 mb-3"></div>

                <div class="flex gap-2">
                    <input type="text"
                        id="tagNameInput"
                        placeholder="Label name"
                        maxlength="50"
                        class="flex-1 px-3 py-1.5 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20">

                    <input type="color"
                        id="tagColorInput"
                        value="#6366f1"
                        class="w-9 h-9 rounded border border-slate-200 cursor-pointer">

                    <button type="button"
                        onclick="addTag()"
                        class="px-3 py-1.5 bg-slate-100 text-slate-600 text-sm rounded-lg hover:bg-slate-200 transition font-medium">
                        Add
                    </button>
                </div>

                <div id="hiddenTags"></div>
            </div>
        </div>

        <!-- === Right Column (sidebar) === -->
        <div class="space-y-4">

            <!-- Status -->
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">
                    Status
                </label>

                <select name="status"
                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">

                    <?php foreach ($statusLabels as $val => $label): ?>
                        <option value="<?= $val ?>" <?= old('status', 'todo') === $val ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <!-- Priority -->
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">
                    Priority
                </label>

                <select name="priority"
                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">

                    <?php foreach ($priorityLabels as $val => $label): ?>
                        <option value="<?= $val ?>" <?= old('priority', 'medium') === $val ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <!-- Project * -->
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">
                    Project <span class="text-red-600">*</span>
                </label>

                <select name="project_id"
                    id="project_id"
                    required
                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">

                    <option value="">Select Project</option>

                    <?php foreach ($projects as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= old('project_id', $project_id) == $p['id'] ? 'selected' : '' ?>>
                            <?= sanitize($p['project_name']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <!-- Milestone -->
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">
                    Milestone
                </label>

                <select name="milestone_id"
                    id="milestone_id"
                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">

                    <option value="">None</option>

                    <?php foreach ($milestones as $m): ?>
                        <option value="<?= $m['id'] ?>"
                            data-project="<?= $m['project_id'] ?>"
                            <?= old('milestone_id') == $m['id'] ? 'selected' : '' ?>>
                            <?= sanitize($m['milestone_name']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <!-- Assignees -->
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">
                    Assignees
                </label>

                <div id="assigneePreview" class="space-y-1.5 mb-3"></div>

                <select id="assigneeSelect"
                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">

                    <option value="">Select person...</option>

                    <?php foreach ($employees as $e): ?>
                        <option value="<?= $e['id'] ?>">
                            <?= sanitize($e['first_name'] . ' ' . $e['last_name']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>

                <div id="hiddenAssignees"></div>
            </div>

            <!-- Dates -->
            <div class="bg-white rounded-xl border border-slate-200 p-4 space-y-3">

                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">
                    Dates
                </label>

                <div>
                    <label class="text-xs text-slate-400 block mb-1">
                        Start Date
                    </label>

                    <input type="date"
                        name="start_date"
                        value="<?= old('start_date') ?>"
                        class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                </div>

                <div>
                    <label class="text-xs text-slate-400 block mb-1">
                        Due Date *
                    </label>

                    <input type="date"
                        name="due_date"
                        value="<?= old('due_date') ?>"
                        required
                        class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                </div>

                <div>
                    <label class="text-xs text-slate-400 block mb-1">
                        Est. Hours
                    </label>

                    <input type="number"
                        name="estimated_hours"
                        value="<?= old('estimated_hours') ?>"
                        min="0"
                        step="any"
                        class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                </div>

            </div>
        </div>
    </div>

    <!-- Submit -->
    <div class="mt-6 flex items-center gap-3">

        <button type="submit"
            class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-lg transition font-medium text-sm shadow-sm">
            <i class="fas fa-plus mr-1.5"></i> Create Task
        </button>

        <a href="<?= url('modules/tasks/index.php') ?>"
            class="px-4 py-2.5 text-sm text-slate-600 hover:text-slate-800 transition font-medium">
            Cancel
        </a>

    </div>
</form>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>

<script>
    // ——— Tags ———
    let tags = [];

    function addTag() {
        const name = document.getElementById('tagNameInput');
        const color = document.getElementById('tagColorInput');

        if (!name.value.trim()) {
            return;
        }

        if (tags.some(t => t.name.toLowerCase() === name.value.toLowerCase())) {
            return;
        }

        tags.push({
            name: name.value.trim(),
            color: color.value
        });

        renderTags();

        name.value = '';
        name.focus();
    }

    function removeTag(idx) {
        tags.splice(idx, 1);
        renderTags();
    }

    function renderTags() {
        const container = document.getElementById('tagsPreview');

        container.innerHTML = tags.map((t, i) =>
            `<span class="tag-pill-input" style="background:${t.color}">
            ${sanitize(t.name)}
            <span class="remove-tag" onclick="removeTag(${i})">&times;</span>
        </span>`
        ).join('');
    }

    function collectTags() {
        const container = document.getElementById('hiddenTags');

        container.innerHTML = '';

        tags.forEach((t, i) => {

            const inpName = document.createElement('input');
            inpName.type = 'hidden';
            inpName.name = `tag_names[${i}]`;
            inpName.value = t.name;

            container.appendChild(inpName);

            const inpColor = document.createElement('input');
            inpColor.type = 'hidden';
            inpColor.name = `tag_colors[${i}]`;
            inpColor.value = t.color;

            container.appendChild(inpColor);
        });
    }


    // ——— Assignees (multi) ———
    let assignees = [];

    document.getElementById('assigneeSelect').addEventListener('change', function() {

        const id = parseInt(this.value);

        if (!id) return;

        if (assignees.some(a => a.id === id)) return;

        const text = this.options[this.selectedIndex].text;

        assignees.push({
            id,
            name: text
        });

        renderAssignees();

        this.value = '';
    });

    function removeAssignee(id) {
        assignees = assignees.filter(a => a.id !== id);
        renderAssignees();
    }

    function renderAssignees() {

        const container = document.getElementById('assigneePreview');

        container.innerHTML = assignees.map(a =>
            `<div class="flex items-center justify-between group">
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 text-xs font-bold">
                    ${a.name.charAt(0).toUpperCase()}
                </div>
                <span class="text-sm text-slate-700">${sanitize(a.name)}</span>
            </div>
            <button type="button"
                    onclick="removeAssignee(${a.id})"
                    class="text-slate-300 hover:text-red-500 transition text-xs">
                &times;
            </button>
        </div>`
        ).join('');

        const hidden = document.getElementById('hiddenAssignees');

        hidden.innerHTML = assignees.map(a =>
            `<input type="hidden" name="assignees[]" value="${a.id}">`
        ).join('');
    }


    // ——— Utility ———
    function sanitize(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }


    // ——— Milestone filter by project ———
    document.getElementById('project_id').addEventListener('change', function() {

        const pid = this.value;

        document.querySelectorAll('#milestone_id option').forEach(opt => {

            if (!opt.value) return;

            opt.style.display = opt.dataset.project === pid ? '' : 'none';
        });

        document.getElementById('milestone_id').value = '';
    });

    document.getElementById('project_id').dispatchEvent(new Event('change'));


    // --------------------------------------------------
    // Create Task - Form Specific Validation
    // --------------------------------------------------

    $(document).ready(function() {

        // Task Title - remove invalid characters while typing/pasting
        $("#createTaskForm input[name='task_title']").on("input", function() {

            this.value = this.value
                .replace(/[^A-Za-z0-9 .,&()_/@#+'-]/g, "")
                .slice(0, 200);
        });


        // Task Description - maximum 1000 characters
        $("#createTaskForm textarea[name='task_description']").on("input", function() {

            this.value = this.value.slice(0, 1000);
        });


        // Tag Name - letters, numbers, spaces and common characters
        $("#tagNameInput").on("input", function() {

            this.value = this.value
                .replace(/[^A-Za-z0-9 ._&@#/-]/g, "")
                .slice(0, 50);
        });


        // Estimated Hours - numbers and decimal point only
        $("#createTaskForm input[name='estimated_hours']").on("input", function() {

            this.value = this.value.replace(/[^0-9.]/g, "");

            // Allow only one decimal point
            const parts = this.value.split(".");

            if (parts.length > 2) {
                this.value = parts[0] + "." + parts.slice(1).join("");
            }
        });


        // Custom validation:
        // Due Date cannot be before Start Date
        $.validator.addMethod(
            "validTaskDateRange",
            function(value, element) {

                const startDate =
                    $("#createTaskForm input[name='start_date']").val();

                if (!startDate || !value) {
                    return true;
                }

                return new Date(value) >= new Date(startDate);
            },
            "Due Date cannot be before Start Date"
        );


        $("#createTaskForm").validate({

            rules: {

                project_id: {
                    required: true
                },

                task_title: {
                    required: true,
                    minlength: 3,
                    maxlength: 200
                },

                task_description: {
                    maxlength: 1000
                },

                start_date: {
                    required: false
                },

                due_date: {
                    required: true,
                    validTaskDateRange: true
                },

                estimated_hours: {
                    min: 0
                },

                status: {
                    required: true
                },

                priority: {
                    required: true
                }
            },


            messages: {

                project_id: {
                    required: "Please select a project"
                },

                task_title: {
                    required: "Task Title is required",
                    minlength: "Task Title must be at least 3 characters",
                    maxlength: "Task Title must not exceed 200 characters"
                },

                task_description: {
                    maxlength: "Task Description must not exceed 1000 characters"
                },

                due_date: {
                    required: "Due Date is required",
                    validTaskDateRange: "Due Date cannot be before Start Date"
                },

                estimated_hours: {
                    number: "Estimated Hours must be a valid number",
                    min: "Estimated Hours cannot be negative"
                },

                status: {
                    required: "Please select task status"
                },

                priority: {
                    required: "Please select task priority"
                }
            },


            errorElement: "span",

            errorClass: "text-red-500 text-sm mt-1 block",


            // Error message below dropdown/input
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

    });

    // --------------------------------------------------
    // Date - Past dates not allowed
    // --------------------------------------------------

    const startDateInput = $("#createTaskForm input[name='start_date']");
    const dueDateInput = $("#createTaskForm input[name='due_date']");

    function getTodayDate() {
        const today = new Date();

        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, "0");
        const day = String(today.getDate()).padStart(2, "0");

        return `${year}-${month}-${day}`;
    }

    const today = getTodayDate();

    // Start Date: Today and future only
    startDateInput.attr("min", today);

    // Due Date: Today and future only
    dueDateInput.attr("min", today);

    // When Start Date is selected
    startDateInput.on("change", function() {

        const startDate = $(this).val();

        if (!startDate) {
            dueDateInput.attr("min", today);
            return;
        }

        // Due Date must be Start Date or later
        dueDateInput.attr("min", startDate);

        // Clear invalid Due Date
        if (dueDateInput.val() && dueDateInput.val() < startDate) {
            dueDateInput.val("");
        }
    });
</script>

<?php require_once '../../includes/footer.php'; ?>