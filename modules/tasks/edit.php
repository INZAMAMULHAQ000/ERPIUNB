<?php
$pageTitle = 'Edit Task';
require_once '../../includes/header.php';

if (!hasPermission('tasks.edit')) {
    die('Access Denied');
}

$db = getDB();
$taskId = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->execute([$taskId]);
$task = $stmt->fetch();

if (!$task) {
    flash('error', 'Task not found');
    to('modules/tasks/index.php');
}

$projects   = $db->query("SELECT id, project_name FROM projects WHERE status IN ('planning', 'active') ORDER BY project_name")->fetchAll();
$milestones = $db->query("SELECT id, milestone_name, project_id FROM milestones WHERE status != 'completed' ORDER BY milestone_name")->fetchAll();
$employees  = $db->query("SELECT id, first_name, last_name FROM employees WHERE status = 'active' ORDER BY first_name")->fetchAll();

// Existing tags and assignees
$taskTags = $db->prepare("SELECT * FROM task_tags WHERE task_id = ? ORDER BY tag_name");
$taskTags->execute([$taskId]);
$existingTags = $taskTags->fetchAll();

$taskAssignees = $db->prepare("SELECT employee_id FROM task_assignees WHERE task_id = ?");
$taskAssignees->execute([$taskId]);
$existingAssignees = array_column($taskAssignees->fetchAll(), 'employee_id');

$statusLabels = ['todo' => 'To Do', 'in_progress' => 'In Progress', 'review' => 'Review', 'testing' => 'Testing', 'completed' => 'Completed', 'blocked' => 'Blocked'];
$priorityLabels = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'];
$errors = restoreFormErrors();
if (empty($errors)) {
    clearOldInput();
}

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
        to('modules/tasks/edit.php?id=' . $taskId);
    }

    // Update task
    $upd = $db->prepare("UPDATE tasks SET project_id=?, milestone_id=?, task_title=?, task_description=?, start_date=?, due_date=?, estimated_hours=?, status=?, priority=? WHERE id=?");
    $upd->execute([
        $data['project_id'],
        $data['milestone_id'] ?: null,
        $data['task_title'],
        $data['task_description'],
        $data['start_date'] ?: null,
        $data['due_date'],
        $data['estimated_hours'] ?: null,
        $data['status'],
        $data['priority'],
        $taskId
    ]);

    // Sync assignees: delete existing, re-insert
    $db->prepare("DELETE FROM task_assignees WHERE task_id = ?")->execute([$taskId]);
    if (!empty($assigneeIds)) {
        $ins = $db->prepare("INSERT INTO task_assignees (task_id, employee_id) VALUES (?, ?)");
        foreach ($assigneeIds as $eid) {
            $ins->execute([$taskId, (int)$eid]);
        }
    }

    // Sync tags: delete existing, re-insert
    $db->prepare("DELETE FROM task_tags WHERE task_id = ?")->execute([$taskId]);
    foreach ($tagNames as $i => $name) {
        if (trim($name)) {
            $color = $tagColors[$i] ?? '#6366f1';
            $db->prepare("INSERT INTO task_tags (task_id, tag_name, tag_color) VALUES (?, ?, ?)")
                ->execute([$taskId, trim($name), $color]);
        }
    }

    logAudit($_SESSION['user_id'], 'update_task', 'tasks');
    flash('success', 'Task updated successfully');
    to('modules/tasks/view.php?id=' . $taskId);
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
        <a href="<?= url('modules/tasks/view.php?id=' . $taskId) ?>" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
            <i class="fas fa-arrow-left mr-1"></i> Back to Task
        </a>
        <h1 class="text-2xl font-bold text-slate-800 mt-1">Edit Task</h1>
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

<form method="POST" id="editTaskForm" class="space-y-4" novalidate>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- === Left Column === -->
        <div class="lg:col-span-2 space-y-5">

            <!-- Title -->
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Task Title <span class="text-red-600">*</span></labe>
                    <input type="text"
                        name="task_title"
                        value="<?= sanitize($task['task_title']) ?>"
                        required
                        maxlength="200"
                        placeholder="Enter task title..."
                        class="w-full px-4 py-2.5 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
            </div>

            <!-- Description -->
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Description</label>
                <textarea name="task_description"
                    rows="4"
                    maxlength="1000"
                    placeholder="Add a description..."
                    class="w-full px-4 py-2.5 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"><?= sanitize($task['task_description']) ?></textarea>
            </div>

            <!-- Tags -->
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Labels</h3>
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

        <!-- === Right Column === -->
        <div class="space-y-4">

            <!-- Status -->
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Status</label>
                <select name="status"
                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    <?php foreach ($statusLabels as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $task['status'] === $val ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Priority -->
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Priority</label>
                <select name="priority"
                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    <?php foreach ($priorityLabels as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $task['priority'] === $val ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Project -->
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Project <span class="text-red-600">*</span></labe>
                    <select name="project_id"
                        required
                        class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                        <option value="">Select Project</option>

                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>"
                                <?= $task['project_id'] == $p['id'] ? 'selected' : '' ?>>
                                <?= sanitize($p['project_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
            </div>

            <!-- Milestone -->
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Milestone</label>
                <select name="milestone_id"
                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    <option value="">None</option>

                    <?php foreach ($milestones as $m): ?>
                        <option value="<?= $m['id'] ?>"
                            data-project="<?= $m['project_id'] ?>"
                            <?= $task['milestone_id'] == $m['id'] ? 'selected' : '' ?>>
                            <?= sanitize($m['milestone_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Assignees -->
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Assignees</label>

                <div id="assigneePreview" class="space-y-1.5 mb-3"></div>

                <select id="assigneeSelect"
                    class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    <option value="">Select person...</option>

                    <?php foreach ($employees as $e): ?>
                        <?php $sel = in_array($e['id'], $existingAssignees) ? ' data-assigned="1"' : ''; ?>

                        <option value="<?= $e['id'] ?>" <?= $sel ?>>
                            <?= sanitize($e['first_name'] . ' ' . $e['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div id="hiddenAssignees"></div>
            </div>

            <!-- Dates -->
            <div class="bg-white rounded-xl border border-slate-200 p-4 space-y-3">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Dates</label>

                <div>
                    <label class="text-xs text-slate-400 block mb-1">Start Date</label>

                    <input type="date"
                        name="start_date"
                        value="<?= $task['start_date'] ?>"
                        class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                </div>

                <div>
                    <label class="text-xs text-slate-400 block mb-1">Due Date <span class="text-red-600">*</span></labe>

                        <input type="date"
                            name="due_date"
                            value="<?= $task['due_date'] ?>"
                            required
                            class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                </div>

                <div>
                    <label class="text-xs text-slate-400 block mb-1">Est. Hours</label>

                    <input type="number"
                        name="estimated_hours"
                        value="<?= $task['estimated_hours'] ?>"
                        min="0"
                        step="0.5"
                        class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                </div>
            </div>
        </div>
    </div>

    <!-- Submit -->
    <div class="mt-6 flex items-center gap-3">
        <button type="submit"
            class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-lg transition font-medium text-sm shadow-sm">
            <i class="fas fa-save mr-1.5"></i> Update Task
        </button>

        <a href="<?= url('modules/tasks/view.php?id=' . $taskId) ?>"
            class="px-4 py-2.5 text-sm text-slate-600 hover:text-slate-800 transition font-medium">
            Cancel
        </a>
    </div>
</form>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.21.0/dist/jquery.validate.min.js"></script>

<script>
    // ——— Initialize tags from existing ———
    const existingTags = <?= json_encode($existingTags) ?>;
    let tags = existingTags.map(t => ({
        name: t.tag_name,
        color: t.tag_color
    }));

    // ——— Initialize assignees from existing ———
    const existingAssigneeIds = <?= json_encode($existingAssignees) ?>;
    const empOptions = <?= json_encode(array_map(fn($e) => [
                            'id' => (int)$e['id'],
                            'name' => $e['first_name'] . ' ' . $e['last_name']
                        ], $employees)) ?>;

    let assignees = empOptions.filter(e => existingAssigneeIds.includes(e.id));

    renderTags();
    renderAssignees();

    function addTag() {
        const nameInput = document.getElementById('tagNameInput');
        const colorInput = document.getElementById('tagColorInput');

        if (!nameInput.value.trim()) return;

        if (tags.some(t => t.name.toLowerCase() === nameInput.value.toLowerCase())) {
            return;
        }

        tags.push({
            name: nameInput.value.trim(),
            color: colorInput.value
        });

        renderTags();

        nameInput.value = '';
        nameInput.focus();
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
            const n = document.createElement('input');
            n.type = 'hidden';
            n.name = `tag_names[${i}]`;
            n.value = t.name;
            container.appendChild(n);

            const c = document.createElement('input');
            c.type = 'hidden';
            c.name = `tag_colors[${i}]`;
            c.value = t.color;
            container.appendChild(c);
        });
    }

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

                <span class="text-sm text-slate-700">
                    ${sanitize(a.name)}
                </span>
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

    function sanitize(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }


    /* =========================================================
       EDIT TASK - FORM SPECIFIC VALIDATION
       ========================================================= */

    $(document).ready(function() {

        /*
         * Task Title
         * Allow letters, numbers, spaces and common task characters.
         */
        $('input[name="task_title"]').on('input', function() {
            this.value = this.value
                .replace(/[^A-Za-z0-9 .,&()_/@#+'-]/g, '')
                .slice(0, 200);
        });


        /*
         * Task Description
         */
        $('textarea[name="task_description"]').on('input', function() {
            this.value = this.value.slice(0, 1000);
        });


        /*
         * Tag Name
         * Remove invalid characters while typing/pasting.
         */
        $('#tagNameInput').on('input', function() {
            this.value = this.value
                .replace(/[^A-Za-z0-9 ._&@#/-]/g, '')
                .slice(0, 50);
        });


        /*
         * Estimated Hours
         * Allow only numbers and one decimal point.
         */
        $('input[name="estimated_hours"]').on('input', function() {

            let value = this.value.replace(/[^0-9.]/g, '');

            const parts = value.split('.');

            if (parts.length > 2) {
                value = parts[0] + '.' + parts.slice(1).join('');
            }

            this.value = value;
        });


        /*
         * Date validation
         * Due Date must not be before Start Date.
         */
        $.validator.addMethod(
            "validTaskDateRange",
            function(value, element) {

                const startDate = $('input[name="start_date"]').val();

                if (!value || !startDate) {
                    return true;
                }

                return new Date(value) >= new Date(startDate);
            },
            "Due date must be on or after the start date"
        );


        /*
         * jQuery Validate
         */
        $('#editTaskForm').validate({

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
                    date: true
                },

                due_date: {
                    required: true,
                    validTaskDateRange: true
                },

                estimated_hours: {
                    number: true,
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
                    required: "Please enter task title",
                    minlength: "Task title must be at least 3 characters",
                    maxlength: "Task title cannot exceed 200 characters"
                },

                task_description: {
                    maxlength: "Description cannot exceed 1000 characters"
                },

                start_date: {
                    date: "Please enter a valid start date"
                },

                due_date: {
                    required: "Please select a due date",
                    validTaskDateRange: "Due date must be on or after the start date"
                },

                estimated_hours: {
                    number: "Please enter a valid number",
                    min: "Estimated hours cannot be negative"
                },

                status: {
                    required: "Please select a status"
                },

                priority: {
                    required: "Please select a priority"
                }
            },

            errorElement: "span",

            errorClass: "text-red-500 text-sm mt-1 block",

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
            },

            submitHandler: function(form) {
                collectTags();
                form.submit();
            }
        });


        /*
         * Revalidate Due Date when Start Date changes.
         */
        $('input[name="start_date"]').on('change', function() {
            $('input[name="due_date"]').valid();
        });


        /*
         * Revalidate Due Date when Due Date changes.
         */
        $('input[name="due_date"]').on('change', function() {
            $(this).valid();
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