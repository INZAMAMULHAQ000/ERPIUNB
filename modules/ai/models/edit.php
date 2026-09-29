<?php
/**
 * Edit AI Model.
 *
 * Form to edit an existing AI model configuration.
 */
$pageTitle = 'Edit AI Model';
require_once __DIR__ . '/../bootstrap.php';

if (!hasPermission('ai.models.manage')) {
    die('Access Denied');
}

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);
$model = AIModelService::getModelById($db, $id);

if (!$model) {
    flash('error', 'Model not found.', 'error');
    header('Location: ' . url('modules/ai/models/index.php'));
    exit;
}

$errors = [];
$providers = AIProviderService::getProviders($db);
$modelTypes = aiGetModelTypes();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $data = [
            'provider_id'              => (int) ($_POST['provider_id'] ?? 0),
            'display_name'             => trim($_POST['display_name'] ?? ''),
            'model_id'                 => trim($_POST['model_id'] ?? ''),
            'description'              => trim($_POST['description'] ?? ''),
            'model_type'               => $_POST['model_type'] ?? 'chat',
            'context_window'           => $_POST['context_window'] ?? '',
            'max_output_tokens'        => $_POST['max_output_tokens'] ?? '',
            'supports_chat'            => isset($_POST['supports_chat']),
            'supports_tool_calling'    => isset($_POST['supports_tool_calling']),
            'supports_vision'          => isset($_POST['supports_vision']),
            'supports_embeddings'      => isset($_POST['supports_embeddings']),
            'supports_structured_output' => isset($_POST['supports_structured_output']),
            'embedding_dimensions'     => !empty($_POST['embedding_dimensions']) ? (int) $_POST['embedding_dimensions'] : null,
            'is_active'                => isset($_POST['is_active']),
            'is_default'               => isset($_POST['is_default']),
            'configuration_json'       => trim($_POST['configuration_json'] ?? ''),
        ];

        $result = AIModelService::updateModel($db, $id, $data, $_SESSION['user_id']);

        if ($result['success']) {
            flash('success', 'Model "' . $data['display_name'] . '" updated successfully.', 'success');
            header('Location: ' . url('modules/ai/models/index.php'));
            exit;
        } else {
            $errors = $result['errors'];
        }
    }
}
?>
<?php require_once AI_APP_ROOT . '/includes/header.php'; ?>
<?php ai_render_module_nav('ai.models'); ?>
<div class="mb-6 flex justify-between items-center flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Edit AI Model</h1>
        <p class="text-gray-600">Update model configuration for <?= sanitize($model['display_name']) ?></p>
    </div>
    <a href="<?= url('modules/ai/models/index.php') ?>" class="text-blue-600 hover:text-blue-900 text-sm">
        <i class="fas fa-arrow-left mr-1"></i>Back to Models
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

        <!-- Provider -->
        <div>
            <label for="provider_id" class="block text-sm font-medium text-gray-700 mb-1">Provider <span class="text-red-600">*</span></labe>
            <select id="provider_id" name="provider_id" required
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <?php foreach ($providers as $p): ?>
                <option value="<?= $p['id'] ?>" <?= ((int)($_POST['provider_id'] ?? $model['provider_id']) === (int)$p['id']) ? 'selected' : '' ?>>
                    <?= sanitize($p['name']) ?> (<?= $p['is_active'] ? 'Active' : 'Inactive' ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Display Name -->
        <div>
            <label for="display_name" class="block text-sm font-medium text-gray-700 mb-1">Display Name <span class="text-red-600">*</span></labe>
            <input type="text" id="display_name" name="display_name" required maxlength="200"
                   value="<?= sanitize($_POST['display_name'] ?? $model['display_name']) ?>"
                   class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>

        <!-- Model ID -->
        <div>
            <label for="model_id" class="block text-sm font-medium text-gray-700 mb-1">Model ID <span class="text-red-600">*</span></labe>
            <input type="text" id="model_id" name="model_id" required maxlength="150"
                   value="<?= sanitize($_POST['model_id'] ?? $model['model_id']) ?>"
                   class="w-full border border-gray-300 rounded-lg px-4 py-2 font-mono text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>

        <!-- Description -->
        <div>
            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea id="description" name="description" rows="2"
                      class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"><?= sanitize($_POST['description'] ?? ($model['description'] ?? '')) ?></textarea>
        </div>

        <!-- Model Type -->
        <div>
            <label for="model_type" class="block text-sm font-medium text-gray-700 mb-1">Model Type</label>
            <select id="model_type" name="model_type"
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <?php foreach ($modelTypes as $type): ?>
                <option value="<?= $type['identifier'] ?>" <?= ($_POST['model_type'] ?? $model['model_type']) === $type['identifier'] ? 'selected' : '' ?>>
                    <?= $type['name'] ?> — <?= $type['description'] ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Token Limits -->
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="context_window" class="block text-sm font-medium text-gray-700 mb-1">Context Window</label>
                <input type="number" id="context_window" name="context_window" min="1"
                       value="<?= sanitize($_POST['context_window'] ?? ($model['context_window'] ?? '')) ?>"
                       class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="max_output_tokens" class="block text-sm font-medium text-gray-700 mb-1">Max Output Tokens</label>
                <input type="number" id="max_output_tokens" name="max_output_tokens" min="1"
                       value="<?= sanitize($_POST['max_output_tokens'] ?? ($model['max_output_tokens'] ?? '')) ?>"
                       class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>

        <!-- Capabilities -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Capabilities</label>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                <label class="flex items-center">
                    <input type="checkbox" name="supports_chat" value="1"
                           <?= !empty($_POST['supports_chat']) || (empty($_POST) && $model['supports_chat']) ? 'checked' : '' ?>
                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="ml-2 text-sm text-gray-700">Chat</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="supports_tool_calling" value="1"
                           <?= !empty($_POST['supports_tool_calling']) || (empty($_POST) && $model['supports_tool_calling']) ? 'checked' : '' ?>
                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="ml-2 text-sm text-gray-700">Tool Calling</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="supports_vision" value="1"
                           <?= !empty($_POST['supports_vision']) || (empty($_POST) && $model['supports_vision']) ? 'checked' : '' ?>
                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="ml-2 text-sm text-gray-700">Vision</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="supports_embeddings" value="1"
                           <?= !empty($_POST['supports_embeddings']) || (empty($_POST) && $model['supports_embeddings']) ? 'checked' : '' ?>
                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                           onchange="document.getElementById('embeddingDimensionsGroup').style.display = this.checked ? 'block' : 'none'">
                    <span class="ml-2 text-sm text-gray-700">Embeddings</span>
                </label>
                <div id="embeddingDimensionsGroup" class="ml-6 mt-1" style="display: <?= !empty($model['supports_embeddings']) ? 'block' : 'none' ?>">
                    <label class="block text-xs text-gray-500 mb-1">Embedding Dimensions</label>
                    <input type="number" name="embedding_dimensions" value="<?= sanitize($model['embedding_dimensions'] ?? '1536') ?>"
                           class="w-32 border border-gray-300 rounded px-2 py-1 text-sm" placeholder="1536">
                    <span class="text-xs text-gray-400 ml-2">e.g., 384, 768, 1536</span>
                </div>
                <label class="flex items-center">
                    <input type="checkbox" name="supports_structured_output" value="1"
                           <?= !empty($_POST['supports_structured_output']) || (empty($_POST) && $model['supports_structured_output']) ? 'checked' : '' ?>
                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="ml-2 text-sm text-gray-700">Structured Output</span>
                </label>
            </div>
        </div>

        <!-- Status & Default -->
        <div class="flex items-center gap-6">
            <label class="flex items-center">
                <input type="checkbox" name="is_active" value="1"
                       <?= !empty($_POST['is_active']) || (empty($_POST) && $model['is_active']) ? 'checked' : '' ?>
                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="ml-2 text-sm text-gray-700">Active</span>
            </label>
            <label class="flex items-center">
                <input type="checkbox" name="is_default" value="1"
                       <?= !empty($_POST['is_default']) || (empty($_POST) && $model['is_default']) ? 'checked' : '' ?>
                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="ml-2 text-sm text-gray-700">Default Model</span>
            </label>
        </div>

        <!-- Metadata -->
        <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-600">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <span class="font-medium">Created:</span>
                    <?= date('M j, Y g:i A', strtotime($model['created_at'])) ?>
                </div>
                <div>
                    <span class="font-medium">Last Updated:</span>
                    <?= date('M j, Y g:i A', strtotime($model['updated_at'])) ?>
                </div>
            </div>
        </div>

        <!-- Submit -->
        <div class="flex justify-end gap-3 pt-4 border-t">
            <a href="<?= url('modules/ai/models/index.php') ?>" class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50">Cancel</a>
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                <i class="fas fa-save mr-1"></i>Save Changes
            </button>
        </div>
    </form>
</div>

<?php require_once AI_APP_ROOT . '/templates/footer.php'; ?>
