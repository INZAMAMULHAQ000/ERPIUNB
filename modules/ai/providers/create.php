<?php
/**
 * Add AI Provider.
 *
 * Form to create a new AI provider configuration.
 */
$pageTitle = 'Add AI Provider';
require_once __DIR__ . '/../bootstrap.php';

if (!hasPermission('ai.providers.create')) {
    die('Access Denied');
}

$db = getDB();
$errors = [];
$success = false;

$providerTypes = aiGetProviderTypes();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $data = [
            'name'              => trim($_POST['name'] ?? ''),
            'provider_type'     => $_POST['provider_type'] ?? '',
            'base_url'          => trim($_POST['base_url'] ?? ''),
            'api_key'           => $_POST['api_key'] ?? '',
            'configuration_json' => trim($_POST['configuration_json'] ?? ''),
            'is_active'         => isset($_POST['is_active']),
            'is_default'        => isset($_POST['is_default']),
        ];

        $result = AIProviderService::createProvider($db, $data, $_SESSION['user_id']);

        if ($result['success']) {
            flash('success', 'Provider "' . $data['name'] . '" created successfully.', 'success');
            header('Location: ' . url('modules/ai/providers/index.php'));
            exit;
        } else {
            $errors = $result['errors'];
        }
    }
}
?>
<?php require_once AI_APP_ROOT . '/includes/header.php'; ?>
<?php ai_render_module_nav('ai.providers'); ?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Add AI Provider</h1>
        <p class="text-gray-600">Configure a new AI service provider</p>
    </div>
    <a href="<?= url('modules/ai/providers/index.php') ?>" class="text-blue-600 hover:text-blue-900 text-sm">
        <i class="fas fa-arrow-left mr-1"></i>Back to Providers
    </a>
</div>

<?php if (!empty($errors)): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
    <ul class="list-disc list-inside">
        <?php foreach ($errors as $error): ?>
        <li><?= sanitize($error) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow p-6 max-w-2xl">
    <form method="POST" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

        <!-- Provider Name -->
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Provider Name <span class="text-red-600">*</span></labe>
            <input type="text" id="name" name="name" required maxlength="150"
                   value="<?= sanitize($_POST['name'] ?? '') ?>"
                   placeholder="e.g., Company OpenAI"
                   class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <p class="text-xs text-gray-500 mt-1">A descriptive name for this provider configuration.</p>
        </div>

        <!-- Provider Type -->
        <div>
            <label for="provider_type" class="block text-sm font-medium text-gray-700 mb-1">Provider Type <span class="text-red-600">*</span></labe>
            <select id="provider_type" name="provider_type" required
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    onchange="updateFormForType(this.value)">
                <option value="">Select a provider type</option>
                <?php foreach ($providerTypes as $type): ?>
                <option value="<?= $type['identifier'] ?>" <?= ($_POST['provider_type'] ?? '') === $type['identifier'] ? 'selected' : '' ?>>
                    <?= $type['name'] ?> — <?= $type['description'] ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Base URL -->
        <div id="baseUrlGroup">
            <label for="base_url" class="block text-sm font-medium text-gray-700 mb-1">Base URL</label>
            <input type="url" id="base_url" name="base_url"
                   value="<?= sanitize($_POST['base_url'] ?? '') ?>"
                   placeholder="https://api.openai.com/v1"
                   class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <p id="baseUrlHint" class="text-xs text-gray-500 mt-1">The API endpoint URL for this provider.</p>
        </div>

        <!-- API Key -->
        <div>
            <label for="api_key" class="block text-sm font-medium text-gray-700 mb-1">API Key</label>
            <input type="password" id="api_key" name="api_key"
                   placeholder="sk-..."
                   autocomplete="off"
                   class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <p class="text-xs text-gray-500 mt-1">The API key will be encrypted before storage. Never displayed again after saving.</p>
        </div>

        <!-- Additional Configuration -->
        <div>
            <label for="configuration_json" class="block text-sm font-medium text-gray-700 mb-1">Additional Configuration (JSON)</label>
            <textarea id="configuration_json" name="configuration_json" rows="4"
                      placeholder='{"temperature": 0.7, "max_tokens": 4096}'
                      class="w-full border border-gray-300 rounded-lg px-4 py-2 font-mono text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"><?= sanitize($_POST['configuration_json'] ?? '') ?></textarea>
            <p class="text-xs text-gray-500 mt-1">Optional JSON configuration for provider-specific settings.</p>
        </div>

        <!-- Status -->
        <div class="flex items-center gap-6">
            <label class="flex items-center">
                <input type="checkbox" name="is_active" value="1"
                       <?= empty($_POST) || !empty($_POST['is_active']) ? 'checked' : '' ?>
                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="ml-2 text-sm text-gray-700">Active</span>
            </label>
            <label class="flex items-center">
                <input type="checkbox" name="is_default" value="1"
                       <?= !empty($_POST['is_default']) ? 'checked' : '' ?>
                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="ml-2 text-sm text-gray-700">Set as Default</span>
            </label>
        </div>

        <!-- Submit -->
        <div class="flex justify-end gap-3 pt-4 border-t">
            <a href="<?= url('modules/ai/providers/index.php') ?>" class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50">Cancel</a>
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                <i class="fas fa-save mr-1"></i>Create Provider
            </button>
        </div>
    </form>
</div>

<script>
const providerTypes = <?= json_encode($providerTypes) ?>;

function updateFormForType(typeId) {
    const type = providerTypes[typeId];
    const baseUrlGroup = document.getElementById('baseUrlGroup');
    const baseUrlInput = document.getElementById('base_url');
    const baseUrlHint = document.getElementById('baseUrlHint');

    if (!type) {
        baseUrlGroup.style.display = '';
        baseUrlInput.required = false;
        return;
    }

    // Show/hide base URL based on type
    baseUrlGroup.style.display = type.supports_base_url ? '' : 'none';
    baseUrlInput.required = type.base_url_required;

    // Set placeholder and hint
    if (type.default_base_url) {
        baseUrlInput.placeholder = type.default_base_url;
    }
    if (type.base_url_required) {
        baseUrlHint.textContent = 'Base URL is required for this provider type.';
        baseUrlHint.classList.add('text-red-500');
    } else {
        baseUrlHint.textContent = 'The API endpoint URL for this provider. Leave blank to use the default.';
        baseUrlHint.classList.remove('text-red-500');
    }
}

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    const currentType = document.getElementById('provider_type').value;
    if (currentType) updateFormForType(currentType);
});
</script>

<?php require_once AI_APP_ROOT . '/templates/footer.php'; ?>
