<?php
/**
 * AI Models List Page.
 *
 * Displays all configured AI models with management actions.
 */
$pageTitle = 'AI Models';
require_once __DIR__ . '/../bootstrap.php';
require_once AI_MODULE_DIR . '/includes/ai_ui.php';

if (!hasPermission('ai.models.view')) {
    die('Access Denied');
}

$db = getDB();
$models = AIModelService::getModels($db);
$providers = AIProviderService::getProviders($db);
$modelTypes = aiGetModelTypes();
$providerMap = array_column($providers, 'name', 'id');
?>

<?php require_once AI_APP_ROOT . '/includes/header.php'; ?>
<?php ai_render_page_styles(); ?>

<div class="ai-page-container">
    <div class="ai-page-header flex justify-between items-center flex-wrap gap-3">
        <div>
            <h1 class="ai-page-title">
                <span class="ai-page-icon"><i class="fas fa-brain"></i></span>
                AI Models
            </h1>
            <p class="ai-page-subtitle">Manage available AI models and configurations</p>
        </div>
        <?php if (hasPermission('ai.models.manage')): ?>
        <a href="<?= url('modules/ai/models/create.php') ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">
            <i class="fas fa-plus mr-1"></i>Add Model
        </a>
        <?php endif; ?>
    </div>

    <?php ai_render_module_nav('ai.models'); ?>

    <!-- Search & Filters -->
    <div class="ai-content-card mb-4">
        <div class="flex items-center gap-3 flex-wrap">
            <div class="flex-1 min-w-[200px]">
                <input type="text" id="searchInput" placeholder="Search models..." class="ai-form-input">
            </div>
            <select id="filterProvider" class="ai-form-select">
                <option value="">All Providers</option>
                <?php foreach ($providers as $p): ?>
                <option value="<?= $p['id'] ?>"><?= sanitize($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="filterStatus" class="ai-form-select">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
    </div>

    <!-- Models Table -->
    <div class="ai-table-wrapper">
        <?php if (empty($models)): ?>
        <div class="ai-content-card text-center">
            <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 bg-gray-100" style="color: var(--ai-accent-2);">
                <i class="fas fa-brain text-2xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-800 mb-2">No Models Configured</h3>
            <p class="text-gray-600 mb-4">Add AI models to your configured providers.</p>
            <?php if (hasPermission('ai.models.manage')): ?>
            <a href="<?= url('modules/ai/models/create.php') ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">
                <i class="fas fa-plus mr-1"></i>Add Model
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full ai-data-table">
                <thead>
                    <tr>
                        <th>Model</th>
                        <th>Provider</th>
                        <th>Type</th>
                        <th>Context</th>
                        <th>Capabilities</th>
                        <th>Status</th>
                        <th>Default</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="modelsTable">
                    <?php foreach ($models as $model): ?>
                    <tr class="model-row"
                        data-name="<?= sanitize(strtolower($model['display_name'] . ' ' . $model['model_id'])) ?>"
                        data-provider="<?= $model['provider_id'] ?>"
                        data-status="<?= $model['is_active'] ? 'active' : 'inactive' ?>">
                        <td>
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 bg-green-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-brain text-green-600"></i>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900"><?= sanitize($model['display_name']) ?></div>
                                    <div class="text-sm text-gray-500 font-mono"><?= sanitize($model['model_id']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?= sanitize($providerMap[$model['provider_id']] ?? 'Unknown') ?>
                        </td>
                        <td>
                            <span class="ai-badge ai-badge-info">
                                <?= sanitize($modelTypes[$model['model_type']]['name'] ?? $model['model_type']) ?>
                            </span>
                        </td>
                        <td>
                            <?= $model['context_window'] ? number_format($model['context_window']) : '—' ?>
                        </td>
                        <td>
                            <div class="flex gap-1 flex-wrap">
                                <?php if ($model['supports_chat']): ?><span class="text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">Chat</span><?php endif; ?>
                                <?php if ($model['supports_tool_calling']): ?><span class="text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">Tools</span><?php endif; ?>
                                <?php if ($model['supports_vision']): ?><span class="text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">Vision</span><?php endif; ?>
                                <?php if ($model['supports_embeddings']): ?><span class="text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">Embed</span><?php endif; ?>
                                <?php if ($model['supports_structured_output']): ?><span class="text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">JSON</span><?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($model['is_active']): ?>
                            <span class="ai-badge ai-badge-success">Active</span>
                            <?php else: ?>
                            <span class="ai-badge">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($model['is_default']): ?>
                            <span class="inline-flex items-center text-sm" style="color: var(--ai-warning); font-weight: 600;">
                                <i class="fas fa-star mr-1"></i>Default
                            </span>
                            <?php else: ?>
                            <span style="color: var(--ai-text-muted);">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <div class="flex items-center justify-end gap-1">
                                <?php if (hasPermission('ai.models.manage')): ?>
                                <a href="<?= url('modules/ai/models/edit.php?id=' . $model['id']) ?>"
                                   class="text-sky-400 hover:text-sky-300 p-1" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($model['is_active']): ?>
                                <button onclick="toggleStatus(<?= $model['id'] ?>, false)"
                                        class="text-yellow-400 hover:text-yellow-300 p-1" title="Disable">
                                    <i class="fas fa-ban"></i>
                                </button>
                                <?php else: ?>
                                <button onclick="toggleStatus(<?= $model['id'] ?>, true)"
                                        class="text-emerald-400 hover:text-emerald-300 p-1" title="Enable">
                                    <i class="fas fa-check-circle"></i>
                                </button>
                                <?php endif; ?>
                                <?php if (!$model['is_default'] && $model['is_active']): ?>
                                <button onclick="setDefault(<?= $model['id'] ?>)"
                                        class="text-yellow-500 hover:text-yellow-400 p-1" title="Set as Default">
                                    <i class="fas fa-star"></i>
                                </button>
                                <?php endif; ?>
                                <button onclick="deleteModel(<?= $model['id'] ?>, '<?= sanitize($model['display_name']) ?>')"
                                        class="text-red-400 hover:text-red-300 p-1" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="ai-content-card max-w-md w-full mx-4">
            <div>
                <div class="flex items-center mb-4">
                    <div class="bg-gray-100 p-3 rounded-full mr-4" style="color: var(--ai-danger);">
                        <i class="fas fa-exclamation-triangle text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Delete Model</h3>
                        <p class="text-sm text-gray-600">This action cannot be undone.</p>
                    </div>
                </div>
                <p class="text-gray-700 mb-6">Are you sure you want to delete <strong id="deleteModelName"></strong>?</p>
                <div class="flex justify-end gap-3">
                    <button onclick="closeDeleteModal()" class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50">Cancel</button>
                    <form id="deleteForm" method="POST" action="<?= url('modules/ai/models/delete.php') ?>">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="id" id="deleteModelId">
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Search & filter
const searchInput = document.getElementById('searchInput');
const filterProvider = document.getElementById('filterProvider');
const filterStatus = document.getElementById('filterStatus');
const rows = document.querySelectorAll('.model-row');

function filterModels() {
    const search = searchInput.value.toLowerCase();
    const provider = filterProvider.value;
    const status = filterStatus.value;
    rows.forEach(row => {
        const name = row.dataset.name;
        const matchSearch = !search || name.includes(search);
        const matchProvider = !provider || row.dataset.provider === provider;
        const matchStatus = !status || row.dataset.status === status;
        row.style.display = (matchSearch && matchProvider && matchStatus) ? '' : 'none';
    });
}
searchInput.addEventListener('input', filterModels);
filterProvider.addEventListener('change', filterModels);
filterStatus.addEventListener('change', filterModels);

function toggleStatus(id, activate) {
    const action = activate ? 'enable' : 'disable';
    if (!confirm('Are you sure you want to ' + action + ' this model?')) return;
    fetch('<?= url('modules/ai/models/toggle_status.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': '<?= csrfToken() ?>' },
        body: 'id=' + id + '&activate=' + (activate ? '1' : '0') + '&csrf_token=<?= csrfToken() ?>'
    }).then(r => r.json()).then(data => {
        if (data.success) location.reload();
        else alert(data.errors ? data.errors.join('\n') : 'Operation failed');
    }).catch(() => alert('Network error'));
}

function setDefault(id) {
    if (!confirm('Set this model as the default?')) return;
    fetch('<?= url('modules/ai/models/set_default.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': '<?= csrfToken() ?>' },
        body: 'id=' + id + '&csrf_token=<?= csrfToken() ?>'
    }).then(r => r.json()).then(data => {
        if (data.success) location.reload();
        else alert(data.errors ? data.errors.join('\n') : 'Operation failed');
    }).catch(() => alert('Network error'));
}

function deleteModel(id, name) {
    document.getElementById('deleteModelId').value = id;
    document.getElementById('deleteModelName').textContent = name;
    document.getElementById('deleteModal').classList.remove('hidden');
    document.getElementById('deleteModal').classList.add('flex');
}
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
    document.getElementById('deleteModal').classList.remove('flex');
}
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});
</script>

<?php require_once AI_APP_ROOT . '/templates/footer.php'; ?>