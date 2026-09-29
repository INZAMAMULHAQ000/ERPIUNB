<?php
/**
 * AI Providers List Page.
 *
 * Displays all configured AI providers with management actions.
 */
$pageTitle = 'AI Providers';
require_once __DIR__ . '/../bootstrap.php';
require_once AI_MODULE_DIR . '/includes/ai_provider_types.php';
require_once AI_MODULE_DIR . '/includes/ai_ui.php';

if (!hasPermission('ai.providers.view')) {
    die('Access Denied');
}

$db = getDB();
$providers = AIProviderService::getProviders($db);
$providerTypes = aiGetProviderTypes();

// Prepare provider type labels
$typeLabels = array_column($providerTypes, 'name', 'identifier');
?>

<?php require_once AI_APP_ROOT . '/includes/header.php'; ?>

<?php ai_render_page_styles(); ?>

<div class="ai-page-container">
    <div class="ai-page-header">
        <div class="flex justify-between items-center flex-wrap gap-3">
            <div>
                <h1 class="ai-page-title">
                    <span class="ai-page-icon"><i class="fas fa-server"></i></span>
                    AI Providers
                </h1>
                <p class="ai-page-subtitle">Manage AI service providers and connections</p>
            </div>
            <?php if (hasPermission('ai.providers.create')): ?>
            <a href="<?= url('modules/ai/providers/create.php') ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">
                <i class="fas fa-plus mr-1"></i>Add Provider
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php ai_render_module_nav('ai.providers'); ?>

    <!-- Search -->
    <div class="ai-content-card mb-4">
        <div class="flex items-center gap-3">
            <div class="flex-1">
                <input type="text" id="searchInput" placeholder="Search providers..." class="ai-form-input">
            </div>
            <div class="flex gap-2">
                <select id="filterType" class="ai-form-select">
                    <option value="">All Types</option>
                    <?php foreach ($providerTypes as $type): ?>
                    <option value="<?= $type['identifier'] ?>"><?= $type['name'] ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="filterStatus" class="ai-form-select">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Providers Table -->
    <div class="ai-table-wrapper">
        <?php if (empty($providers)): ?>
        <div class="p-8 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full mb-4 bg-gray-100" style="color: var(--ai-accent-2);">
                <i class="fas fa-server text-2xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-800 mb-2">No Providers Configured</h3>
            <p class="text-gray-600 mb-4">Get started by adding your first AI provider.</p>
            <?php if (hasPermission('ai.providers.create')): ?>
            <a href="<?= url('modules/ai/providers/create.php') ?>" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">
                <i class="fas fa-plus mr-1"></i>Add Provider
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="ai-data-table">
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Type</th>
                        <th>Base URL</th>
                        <th>Credentials</th>
                        <th>Connection</th>
                        <th>Status</th>
                        <th>Default</th>
                        <th>Updated</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="providersTable">
                    <?php foreach ($providers as $provider): ?>
                    <tr class="provider-row"
                        data-name="<?= sanitize(strtolower($provider['name'])) ?>"
                        data-type="<?= sanitize($provider['provider_type']) ?>"
                        data-status="<?= $provider['is_active'] ? 'active' : 'inactive' ?>">
                        <td>
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 rounded-full flex items-center justify-center bg-gray-100" style="color: var(--ai-accent-2);">
                                    <i class="fas fa-server"></i>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900"><?= sanitize($provider['name']) ?></div>
                                    <div class="text-sm text-gray-500"><?= sanitize($provider['slug']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="ai-badge ai-badge-info">
                                <?= sanitize($typeLabels[$provider['provider_type']] ?? $provider['provider_type']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($provider['base_url'])): ?>
                            <span title="<?= sanitize($provider['base_url']) ?>"><?= sanitize(substr($provider['base_url'], 0, 40)) ?><?= strlen($provider['base_url']) > 40 ? '...' : '' ?></span>
                            <?php else: ?>
                            <span class="text-gray-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $credStatus = AIProviderService::getCredentialStatus($db, $provider['id']);
                            if ($credStatus['configured']): ?>
                            <span class="inline-flex items-center text-sm ai-badge-success">
                                <i class="fas fa-check-circle"></i><?= $credStatus['masked'] ?>
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center text-sm text-gray-400">
                                <i class="fas fa-times-circle"></i>Not configured
                            </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $connStatus = $provider['last_connection_status'] ?? 'never_tested';
                            $connColors = ['success' => 'ai-badge ai-badge-success', 'failed' => 'ai-badge ai-badge-danger', 'never_tested' => 'ai-badge ai-badge-info'];
                            $connLabels = ['success' => 'Connected', 'failed' => 'Failed', 'never_tested' => 'Not tested'];
                            $connIcons = ['success' => 'fa-check-circle', 'failed' => 'fa-times-circle', 'never_tested' => 'fa-question-circle'];
                            ?>
                            <span class="<?= $connColors[$connStatus] ?? $connColors['never_tested'] ?>" title="<?= sanitize($provider['last_connection_message'] ?? '') ?>">
                                <i class="fas <?= $connIcons[$connStatus] ?? $connIcons['never_tested'] ?>"></i><?= $connLabels[$connStatus] ?? 'Unknown' ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($provider['is_active']): ?>
                            <span class="ai-badge ai-badge-success">Active</span>
                            <?php else: ?>
                            <span class="ai-badge ai-badge-info">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($provider['is_default']): ?>
                            <span class="inline-flex items-center text-sm text-yellow-600 font-medium">
                                <i class="fas fa-star"></i>Default
                            </span>
                            <?php else: ?>
                            <span class="text-gray-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= date('M j, Y', strtotime($provider['updated_at'])) ?>
                        </td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-1">
                                <?php if (hasPermission('ai.providers.update')): ?>
                                <a href="<?= url('modules/ai/providers/edit.php?id=' . $provider['id']) ?>"
                                   class="text-blue-600 hover:text-blue-900 p-1" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>

                                <?php if ($provider['is_active']): ?>
                                <button onclick="toggleStatus(<?= $provider['id'] ?>, false)"
                                        class="text-yellow-600 hover:text-yellow-900 p-1" title="Disable">
                                    <i class="fas fa-ban"></i>
                                </button>
                                <?php else: ?>
                                <button onclick="toggleStatus(<?= $provider['id'] ?>, true)"
                                        class="text-green-600 hover:text-green-900 p-1" title="Enable">
                                    <i class="fas fa-check-circle"></i>
                                </button>
                                <?php endif; ?>

                                <?php if ($provider['is_active']): ?>
                                <button onclick="testConnection(<?= $provider['id'] ?>)"
                                        class="text-green-600 hover:text-green-900 p-1" title="Test Connection">
                                    <i class="fas fa-plug"></i>
                                </button>
                                <button onclick="discoverModels(<?= $provider['id'] ?>)"
                                        class="text-purple-600 hover:text-purple-900 p-1" title="Discover Models">
                                    <i class="fas fa-search"></i>
                                </button>
                                <?php endif; ?>

                                <?php if (!$provider['is_default'] && $provider['is_active']): ?>
                                <button onclick="setDefault(<?= $provider['id'] ?>)"
                                        class="text-yellow-500 hover:text-yellow-700 p-1" title="Set as Default">
                                    <i class="fas fa-star"></i>
                                </button>
                                <?php endif; ?>
                                <?php endif; ?>

                                <?php if (hasPermission('ai.providers.delete')): ?>
                                <button onclick="deleteProvider(<?= $provider['id'] ?>, '<?= sanitize($provider['name']) ?>')"
                                        class="text-red-600 hover:text-red-900 p-1" title="Delete">
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

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="ai-content-card max-w-md w-full mx-4">
            <div>
                <div class="flex items-center mb-4">
                    <div class="bg-gray-100 p-3 rounded-full mr-4" style="color: var(--ai-danger);">
                        <i class="fas fa-exclamation-triangle text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Delete Provider</h3>
                        <p class="text-sm text-gray-600">This action cannot be undone.</p>
                    </div>
                </div>
                <p class="text-gray-700 mb-6">Are you sure you want to delete <strong id="deleteProviderName"></strong>?</p>
                <div class="flex justify-end gap-3">
                    <button onclick="closeDeleteModal()" class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50">Cancel</button>
                    <form id="deleteForm" method="POST" action="<?= url('modules/ai/providers/delete.php') ?>">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="id" id="deleteProviderId">
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Search and filter functionality
const searchInput = document.getElementById('searchInput');
const filterType = document.getElementById('filterType');
const filterStatus = document.getElementById('filterStatus');
const rows = document.querySelectorAll('.provider-row');

function filterProviders() {
    const search = searchInput.value.toLowerCase();
    const type = filterType.value;
    const status = filterStatus.value;

    rows.forEach(row => {
        const name = row.dataset.name;
        const rowType = row.dataset.type;
        const rowStatus = row.dataset.status;

        const matchSearch = !search || name.includes(search);
        const matchType = !type || rowType === type;
        const matchStatus = !status || rowStatus === status;

        row.style.display = (matchSearch && matchType && matchStatus) ? '' : 'none';
    });
}

searchInput.addEventListener('input', filterProviders);
filterType.addEventListener('change', filterProviders);
filterStatus.addEventListener('change', filterProviders);

// Toggle status
function toggleStatus(id, activate) {
    const action = activate ? 'enable' : 'disable';
    if (!confirm('Are you sure you want to ' + action + ' this provider?')) return;

    fetch('<?= url('modules/ai/providers/toggle_status.php') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': '<?= csrfToken() ?>'
        },
        body: 'id=' + id + '&activate=' + (activate ? '1' : '0') + '&csrf_token=<?= csrfToken() ?>'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.errors ? data.errors.join('\n') : 'Operation failed');
        }
    })
    .catch(err => alert('Network error'));
}

// Set default
function setDefault(id) {
    if (!confirm('Set this provider as the default?')) return;

    fetch('<?= url('modules/ai/providers/set_default.php') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': '<?= csrfToken() ?>'
        },
        body: 'id=' + id + '&csrf_token=<?= csrfToken() ?>'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.errors ? data.errors.join('\n') : 'Operation failed');
        }
    })
    .catch(err => alert('Network error'));
}

// Delete provider
function deleteProvider(id, name) {
    document.getElementById('deleteProviderId').value = id;
    document.getElementById('deleteProviderName').textContent = name;
    document.getElementById('deleteModal').classList.remove('hidden');
    document.getElementById('deleteModal').classList.add('flex');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
    document.getElementById('deleteModal').classList.remove('flex');
}

// Close modal on outside click
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});

// Test Connection
function testConnection(id) {
    const btn = event.target.closest('button');
    const origHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.disabled = true;

    fetch('<?= url('modules/ai/providers/test_connection.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': '<?= csrfToken() ?>' },
        body: 'id=' + id + '&csrf_token=<?= csrfToken() ?>'
    })
    .then(r => r.json())
    .then(data => {
        btn.innerHTML = origHtml;
        btn.disabled = false;
        if (data.success) {
            alert('✓ ' + (data.message || 'Connected successfully'));
        } else {
            alert('✗ ' + (data.message || 'Connection failed'));
        }
        location.reload();
    })
    .catch(() => { btn.innerHTML = origHtml; btn.disabled = false; alert('Network error'); });
}

// Discover Models
function discoverModels(id) {
    if (!confirm('Discover available models from this provider?')) return;

    const btn = event.target.closest('button');
    const origHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.disabled = true;

    fetch('<?= url('modules/ai/providers/discover_models.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': '<?= csrfToken() ?>' },
        body: 'id=' + id + '&csrf_token=<?= csrfToken() ?>'
    })
    .then(r => r.json())
    .then(data => {
        btn.innerHTML = origHtml;
        btn.disabled = false;
        if (data.success && data.models && data.models.length > 0) {
            showDiscoveryResults(id, data.models);
        } else if (data.error) {
            alert(data.error);
        } else {
            alert('No models found.');
        }
    })
    .catch(() => { btn.innerHTML = origHtml; btn.disabled = false; alert('Network error'); });
}

// Show Discovery Results Modal
let currentDiscoveredModels = [];
let currentDiscoveryProviderId = null;

function showDiscoveryResults(providerId, models) {
    currentDiscoveredModels = models;
    currentDiscoveryProviderId = providerId;
    document.getElementById('discoveryModal')?.remove();

    const hasFreeModels = models.some(m => m.is_free || m.display_name.startsWith('[FREE]'));

    let html = `
    <div id="discoveryModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[85vh] flex flex-col overflow-hidden">
            <div class="p-4 border-b bg-gray-50 flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Discovered Models (${models.length})</h3>
                    <p class="text-xs text-gray-600">Select models to import into your AI Assistant</p>
                </div>
                <button onclick="document.getElementById('discoveryModal').remove()" class="text-gray-400 hover:text-gray-600 text-lg">&times;</button>
            </div>
            
            <div class="p-3 border-b bg-white flex flex-wrap items-center justify-between gap-2">
                <input type="text" id="modalSearchInput" placeholder="Filter models (e.g., free, llama, deepseek)..."
                       class="border border-gray-300 rounded px-3 py-1.5 text-sm flex-1 min-w-[200px]"
                       oninput="filterModalModels(this.value)">
                <div class="flex gap-2">
                    ${hasFreeModels ? '<button type="button" onclick="selectFreeModels()" class="px-2.5 py-1 bg-green-100 text-green-800 hover:bg-green-200 text-xs font-medium rounded border border-green-300"><i class="fas fa-gift mr-1"></i>Select All Free</button>' : ''}
                    <button type="button" onclick="selectAllNewModels(true)" class="px-2.5 py-1 bg-blue-50 text-blue-700 hover:bg-blue-100 text-xs font-medium rounded border border-blue-200">Select All New</button>
                    <button type="button" onclick="selectAllNewModels(false)" class="px-2.5 py-1 bg-gray-100 text-gray-700 hover:bg-gray-200 text-xs font-medium rounded border border-gray-300">Deselect All</button>
                </div>
            </div>

            <div class="p-4 overflow-y-auto flex-1">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase text-gray-500 border-b">
                            <th class="py-2 pr-3 w-12 text-center">Select</th>
                            <th class="py-2 pr-3">Model ID</th>
                            <th class="py-2 pr-3">Display Name</th>
                            <th class="py-2 pr-3">Status</th>
                        </tr>
                    </thead>
                    <tbody id="modalModelsBody">
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t bg-gray-50 flex justify-between items-center">
                <button onclick="document.getElementById('discoveryModal').remove()" class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-white text-sm">Cancel</button>
                <button onclick="importSelectedModels()" class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-medium text-sm">
                    <i class="fas fa-download mr-1"></i>Import Selected
                </button>
            </div>
        </div>
    </div>`;

    document.body.insertAdjacentHTML('beforeend', html);
    renderModalRows(models);
}

function renderModalRows(modelsToRender) {
    const tbody = document.getElementById('modalModelsBody');
    if (!tbody) return;

    if (modelsToRender.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="py-6 text-center text-gray-400">No matching models found.</td></tr>';
        return;
    }

    tbody.innerHTML = modelsToRender.map(m => {
        const i = currentDiscoveredModels.findIndex(orig => orig.model_id === m.model_id);
        const disabled = m.is_existing ? 'disabled' : '';
        const checked = m.is_existing ? 'checked' : '';
        const isFree = m.is_free || m.display_name.startsWith('[FREE]');
        const statusBadge = m.is_existing 
            ? '<span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600">Already Added</span>'
            : (isFree 
                ? '<span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-800 font-semibold">FREE</span>' 
                : '<span class="px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-700">New</span>');

        return `
        <tr class="border-b hover:bg-gray-50 modal-model-row" data-index="${i}" data-free="${isFree ? '1' : '0'}">
            <td class="py-2.5 pr-3 text-center">
                <input type="checkbox" class="discover-check rounded" value="${i}" ${checked} ${disabled} data-free="${isFree ? '1' : '0'}">
            </td>
            <td class="py-2.5 pr-3 font-mono text-xs text-gray-800 font-medium">${escapeHtml(m.model_id)}</td>
            <td class="py-2.5 pr-3 text-gray-900">${escapeHtml(m.display_name)}</td>
            <td class="py-2.5 pr-3">${statusBadge}</td>
        </tr>`;
    }).join('');
}

function filterModalModels(q) {
    const search = q.toLowerCase().trim();
    if (!search) {
        renderModalRows(currentDiscoveredModels);
        return;
    }
    const filtered = currentDiscoveredModels.filter(m => 
        m.model_id.toLowerCase().includes(search) || 
        m.display_name.toLowerCase().includes(search) ||
        (search === 'free' && (m.is_free || m.display_name.startsWith('[FREE]')))
    );
    renderModalRows(filtered);
}

function selectFreeModels() {
    document.querySelectorAll('.discover-check:not(:disabled)').forEach(cb => {
        cb.checked = (cb.dataset.free === '1');
    });
}

function selectAllNewModels(select) {
    document.querySelectorAll('.discover-check:not(:disabled)').forEach(cb => {
        cb.checked = select;
    });
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Import Selected Models
function importSelectedModels() {
    const checks = document.querySelectorAll('.discover-check:checked:not(:disabled)');
    if (checks.length === 0) { alert('No new models selected.'); return; }

    const selected = [];
    checks.forEach(c => { 
        const m = currentDiscoveredModels[parseInt(c.value)];
        if (m) selected.push(m);
    });

    const btn = event?.target?.closest('button');
    if (btn) { btn.disabled = true; btn.textContent = 'Importing...'; }

    fetch('<?= url('modules/ai/providers/import_models.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= csrfToken() ?>' },
        body: JSON.stringify({ provider_id: currentDiscoveryProviderId, models: selected })
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('discoveryModal')?.remove();
        if (data.success) {
            alert('✓ Imported ' + data.imported + ' models successfully.');
            location.reload();
        } else {
            alert('✗ ' + (data.message || 'Import failed.'));
        }
    })
    .catch(() => alert('Network error during import.'));
}
</script>

<?php require_once AI_APP_ROOT . '/templates/footer.php'; ?>
