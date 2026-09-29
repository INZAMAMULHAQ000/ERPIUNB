<?php
$pageTitle = 'AI Integrations';
require_once __DIR__ . '/../bootstrap.php';
require_once AI_MODULE_DIR . '/services/AIIntegrationService.php';

if (!hasPermission('ai.integrations.view') && !hasPermission('ai.settings.manage')) {
    die('Access Denied');
}

$db = getDB();
$companyId = isset($_SESSION['company_id']) ? (int) $_SESSION['company_id'] : 1;
AIIntegrationService::ensureDefaults($db);

$summary = AIIntegrationService::getDashboardSummary($db, $companyId);
$connectors = AIIntegrationService::listConnectors($db);
$capabilities = AIIntegrationService::listCapabilities($db);
$connections = AIIntegrationService::listConnections($db, $companyId);
$webhooks = AIIntegrationService::listWebhooks($db, $companyId);
$mappings = AIIntegrationService::listSyncMappings($db, $companyId);
$syncJobs = AIIntegrationService::listSyncJobs($db, $companyId);
$policies = AIIntegrationService::getPolicies($db, $companyId);
$activity = AIIntegrationService::getActivity($db, $companyId, 15);
$health = AIIntegrationService::getHealth($db, $companyId);
?>

<?php require_once AI_APP_ROOT . '/includes/header.php'; ?>
<?php ai_render_module_nav('ai.integrations'); ?>

<div class="mb-6 flex flex-wrap justify-between items-start gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">AI Integrations</h1>
        <p class="text-gray-600 mt-1">Registered connectors, company connections, secured credentials, restricted endpoints, sync previews, and webhook delivery.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="<?= url('modules/ai/management/index.php') ?>" class="bg-white border border-gray-200 px-3 py-2 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
            <i class="fas fa-shield-alt mr-1 text-slate-500"></i> Management
        </a>
        <a href="<?= url('modules/ai/workflows/index.php') ?>" class="bg-white border border-gray-200 px-3 py-2 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
            <i class="fas fa-diagram-project mr-1 text-blue-500"></i> Workflows
        </a>
        <a href="<?= url('modules/ai/command-center/index.php') ?>" class="bg-white border border-gray-200 px-3 py-2 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
            <i class="fas fa-terminal mr-1 text-indigo-500"></i> Command Center
        </a>
    </div>
</div>

<nav class="flex flex-wrap gap-2 mb-6">
    <a href="#connectors" class="bg-white border px-3 py-2 rounded-lg text-sm text-gray-700">Connectors</a>
    <a href="#connections" class="bg-white border px-3 py-2 rounded-lg text-sm text-gray-700">Connections</a>
    <a href="#credentials" class="bg-white border px-3 py-2 rounded-lg text-sm text-gray-700">Credentials</a>
    <a href="#capabilities" class="bg-white border px-3 py-2 rounded-lg text-sm text-gray-700">Capabilities</a>
    <a href="#webhooks" class="bg-white border px-3 py-2 rounded-lg text-sm text-gray-700">Webhooks</a>
    <a href="#sync-jobs" class="bg-white border px-3 py-2 rounded-lg text-sm text-gray-700">Sync Jobs</a>
    <a href="#activity" class="bg-white border px-3 py-2 rounded-lg text-sm text-gray-700">Activity</a>
    <a href="#health" class="bg-white border px-3 py-2 rounded-lg text-sm text-gray-700">Health</a>
</nav>

<div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <div class="text-xs uppercase text-gray-500 font-semibold">Connectors</div>
        <div class="text-2xl font-bold text-gray-800 mt-1"><?= (int) ($summary['connectors'] ?? 0) ?></div>
    </div>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <div class="text-xs uppercase text-gray-500 font-semibold">Active Connections</div>
        <div class="text-2xl font-bold text-emerald-600 mt-1"><?= (int) ($summary['active_connections'] ?? 0) ?></div>
    </div>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <div class="text-xs uppercase text-gray-500 font-semibold">Failed Connections</div>
        <div class="text-2xl font-bold text-rose-600 mt-1"><?= (int) ($summary['failed_connections'] ?? 0) ?></div>
    </div>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <div class="text-xs uppercase text-gray-500 font-semibold">Webhook Failures</div>
        <div class="text-2xl font-bold text-amber-600 mt-1"><?= (int) ($summary['webhook_failures'] ?? 0) ?></div>
    </div>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <div class="text-xs uppercase text-gray-500 font-semibold">Sync Jobs</div>
        <div class="text-2xl font-bold text-indigo-600 mt-1"><?= (int) ($summary['sync_jobs'] ?? 0) ?></div>
    </div>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <div class="text-xs uppercase text-gray-500 font-semibold">Activity 30d</div>
        <div class="text-2xl font-bold text-slate-800 mt-1"><?= (int) ($summary['activity_30d'] ?? 0) ?></div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
    <section id="connectors" class="xl:col-span-2 bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-800">Registered Connectors</h2>
            <p class="text-sm text-gray-500 mt-1">Server-side registered only. No arbitrary runtime connector code.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                    <tr>
                        <th class="text-left px-5 py-3">Connector</th>
                        <th class="text-left px-5 py-3">Category</th>
                        <th class="text-left px-5 py-3">Status</th>
                        <th class="text-left px-5 py-3">Safety</th>
                        <th class="text-left px-5 py-3">Capabilities</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($connectors as $connector): ?>
                        <tr>
                            <td class="px-5 py-4">
                                <div class="font-medium text-gray-800"><?= htmlspecialchars($connector['name']) ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($connector['connector_key']) ?></div>
                            </td>
                            <td class="px-5 py-4"><?= htmlspecialchars($connector['category']) ?></td>
                            <td class="px-5 py-4"><?= htmlspecialchars($connector['status']) ?></td>
                            <td class="px-5 py-4"><?= htmlspecialchars($connector['safety_classification']) ?></td>
                            <td class="px-5 py-4 text-xs text-gray-600"><?= htmlspecialchars($connector['supported_capabilities_json']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-800">Integration Policies</h2>
            <p class="text-sm text-gray-500 mt-1">Company-scoped policy enforcement for external access.</p>
        </div>
        <div class="p-5 space-y-3">
            <?php foreach ($policies as $policy): ?>
                <label class="flex items-start justify-between gap-3 border border-gray-100 rounded-lg p-3">
                    <div>
                        <div class="font-medium text-sm text-gray-800"><?= htmlspecialchars($policy['policy_name']) ?></div>
                        <div class="text-xs text-gray-500"><?= htmlspecialchars($policy['policy_key']) ?></div>
                    </div>
                    <input type="checkbox" class="policy-toggle mt-1" data-policy="<?= htmlspecialchars($policy['policy_key'], ENT_QUOTES) ?>" <?= !empty($policy['enabled']) ? 'checked' : '' ?>>
                </label>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
    <section id="connections" class="bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-800">Connections</h2>
            <p class="text-sm text-gray-500 mt-1">Company-scoped configured connections with explicit capability enablement.</p>
        </div>
        <div class="p-5">
            <form id="connectionForm" class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-5">
                <input type="hidden" name="id" value="">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Connector</label>
                    <select name="connector_key" class="w-full border rounded-lg px-3 py-2 text-sm">
                        <?php foreach ($connectors as $connector): ?>
                            <option value="<?= htmlspecialchars($connector['connector_key']) ?>"><?= htmlspecialchars($connector['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Display Name</label>
                    <input type="text" name="display_name" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Primary external API">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                    <select name="status" class="w-full border rounded-lg px-3 py-2 text-sm">
                        <option value="draft">draft</option>
                        <option value="connecting">connecting</option>
                        <option value="active">active</option>
                        <option value="failed">failed</option>
                        <option value="disabled">disabled</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Auth State</label>
                    <select name="authentication_state" class="w-full border rounded-lg px-3 py-2 text-sm">
                        <option value="unconfigured">unconfigured</option>
                        <option value="configured">configured</option>
                        <option value="expired">expired</option>
                        <option value="failed">failed</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Approved Base URL</label>
                    <input type="text" name="approved_base_url" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="https://api.example.com">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Enabled Capabilities</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-40 overflow-auto border rounded-lg p-3 bg-gray-50">
                        <?php foreach ($capabilities as $capability): ?>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="enabled_capabilities[]" value="<?= htmlspecialchars($capability['capability_key']) ?>">
                                <span><?= htmlspecialchars($capability['capability_key']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="md:col-span-2 flex gap-2">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">Save Connection</button>
                    <button type="button" id="reloadConnections" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-200">Refresh</button>
                </div>
            </form>

            <div id="connectionsList" class="space-y-3">
                <?php foreach ($connections as $connection): ?>
                    <div class="border border-gray-100 rounded-lg p-4">
                        <div class="flex justify-between gap-3">
                            <div>
                                <div class="font-medium text-gray-800"><?= htmlspecialchars($connection['display_name']) ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($connection['connector_key']) ?> · <?= htmlspecialchars($connection['status']) ?></div>
                            </div>
                            <button type="button" class="revoke-connection text-xs text-rose-600" data-id="<?= (int) $connection['id'] ?>">Revoke</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section id="credentials" class="bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-800">Credential Rotation</h2>
            <p class="text-sm text-gray-500 mt-1">Secrets are encrypted at rest and never re-exposed in plaintext.</p>
        </div>
        <div class="p-5 space-y-4">
            <form id="credentialForm" class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Connection</label>
                    <select name="connection_id" class="w-full border rounded-lg px-3 py-2 text-sm">
                        <?php foreach ($connections as $connection): ?>
                            <option value="<?= (int) $connection['id'] ?>"><?= htmlspecialchars($connection['display_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Auth Strategy</label>
                        <select name="auth_strategy" class="w-full border rounded-lg px-3 py-2 text-sm">
                            <option value="api_key">api_key</option>
                            <option value="bearer_token">bearer_token</option>
                            <option value="basic_auth">basic_auth</option>
                            <option value="custom_header">custom_header</option>
                            <option value="oauth2">oauth2</option>
                            <option value="webhook_secret">webhook_secret</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Credential Type</label>
                        <input type="text" name="credential_type" class="w-full border rounded-lg px-3 py-2 text-sm" value="connector_auth">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Primary Secret</label>
                    <input type="password" name="secret" class="w-full border rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Secondary Secret</label>
                    <input type="password" name="secondary_secret" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Optional">
                </div>
                <button type="submit" class="bg-emerald-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-emerald-700">Rotate Credential</button>
            </form>

            <div class="text-xs text-gray-500">
                Plaintext is accepted only during rotation and is then encrypted with the existing AI credential helper.
            </div>
        </div>
    </section>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
    <section id="capabilities" class="bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-800">Capabilities & Restricted Endpoints</h2>
            <p class="text-sm text-gray-500 mt-1">Every external operation must be explicitly registered before use.</p>
        </div>
        <div class="p-5">
            <form id="endpointForm" class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-5">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">REST Connection</label>
                    <select name="connection_id" class="w-full border rounded-lg px-3 py-2 text-sm">
                        <?php foreach ($connections as $connection): if ($connection['connector_key'] !== 'generic_rest') continue; ?>
                            <option value="<?= (int) $connection['id'] ?>"><?= htmlspecialchars($connection['display_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Capability</label>
                    <select name="capability_key" class="w-full border rounded-lg px-3 py-2 text-sm">
                        <?php foreach ($capabilities as $capability): if ($capability['connector_key'] !== 'generic_rest') continue; ?>
                            <option value="<?= htmlspecialchars($capability['capability_key']) ?>"><?= htmlspecialchars($capability['capability_key']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Endpoint Key</label>
                    <input type="text" name="endpoint_key" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="vendor.preview">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Name</label>
                    <input type="text" name="name" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Supplier Preview">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Approved Base URL</label>
                    <input type="text" name="approved_base_url" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="https://api.example.com">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Approved Path</label>
                    <input type="text" name="approved_path" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="/inventory/status">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">HTTP Method</label>
                    <select name="http_method" class="w-full border rounded-lg px-3 py-2 text-sm">
                        <option>GET</option>
                        <option>POST</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Allowed Query Params</label>
                    <input type="text" name="allowed_query_params" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="item_id,warehouse_id">
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-indigo-700">Save Restricted Endpoint</button>
                </div>
            </form>

            <div class="space-y-3 max-h-72 overflow-auto">
                <?php foreach ($capabilities as $capability): ?>
                    <div class="border border-gray-100 rounded-lg p-3">
                        <div class="font-medium text-sm text-gray-800"><?= htmlspecialchars($capability['capability_key']) ?></div>
                        <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($capability['description']) ?></div>
                        <div class="text-xs text-gray-400 mt-1"><?= htmlspecialchars($capability['operation_type']) ?> · <?= htmlspecialchars($capability['required_permission'] ?? '') ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section id="webhooks" class="bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-800">Webhooks</h2>
            <p class="text-sm text-gray-500 mt-1">Approved destinations only, with HTTPS, signing, retries, and SSRF protection.</p>
        </div>
        <div class="p-5">
            <form id="webhookForm" class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-5">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Webhook Key</label>
                    <input type="text" name="webhook_key" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="procurement_notify">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Name</label>
                    <input type="text" name="name" class="w-full border rounded-lg px-3 py-2 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Destination URL</label>
                    <input type="text" name="destination_url" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="https://hooks.example.com/erp">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Event Source</label>
                    <select name="event_source" class="w-full border rounded-lg px-3 py-2 text-sm">
                        <option value="workflow.completed">workflow.completed</option>
                        <option value="workflow.failed">workflow.failed</option>
                        <option value="task.created">task.created</option>
                        <option value="ai.finding.created">ai.finding.created</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                    <select name="status" class="w-full border rounded-lg px-3 py-2 text-sm">
                        <option value="draft">draft</option>
                        <option value="active">active</option>
                        <option value="disabled">disabled</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Secret</label>
                    <input type="password" name="secret" class="w-full border rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Signature Header</label>
                    <input type="text" name="signature_header" class="w-full border rounded-lg px-3 py-2 text-sm" value="X-AI-Signature">
                </div>
                <div class="md:col-span-2 flex items-center gap-2">
                    <input type="checkbox" name="allow_internal_destination" value="1">
                    <span class="text-sm text-gray-600">Allow internal destination</span>
                </div>
                <div class="md:col-span-2 flex gap-2">
                    <button type="submit" class="bg-slate-800 text-white px-4 py-2 rounded-lg text-sm hover:bg-black">Save Webhook</button>
                    <button type="button" id="sendTestWebhook" class="bg-amber-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-amber-600">Send Test Webhook</button>
                </div>
            </form>

            <div class="space-y-3 max-h-72 overflow-auto">
                <?php foreach ($webhooks as $webhook): ?>
                    <div class="border border-gray-100 rounded-lg p-3">
                        <div class="font-medium text-sm text-gray-800"><?= htmlspecialchars($webhook['name']) ?></div>
                        <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($webhook['webhook_key']) ?> · <?= htmlspecialchars($webhook['event_source']) ?></div>
                        <div class="text-xs text-gray-400 mt-1"><?= htmlspecialchars($webhook['destination_url']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
    <section id="sync-jobs" class="bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-800">Sync Preview & Jobs</h2>
            <p class="text-sm text-gray-500 mt-1">Preview new records, updates, conflicts, and errors before any apply step.</p>
        </div>
        <div class="p-5">
            <form id="mappingForm" class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-5">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Mapping Key</label>
                    <input type="text" name="mapping_key" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="vendor_import_map">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Name</label>
                    <input type="text" name="name" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Supplier Import">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Target Entity</label>
                    <select name="target_entity" class="w-full border rounded-lg px-3 py-2 text-sm">
                        <option value="vendors">vendors</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Connection</label>
                    <select name="connection_id" class="w-full border rounded-lg px-3 py-2 text-sm">
                        <?php foreach ($connections as $connection): ?>
                            <option value="<?= (int) $connection['id'] ?>"><?= htmlspecialchars($connection['display_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Rules JSON</label>
                    <textarea name="rules_json" rows="5" class="w-full border rounded-lg px-3 py-2 text-sm">{"fields":[{"source":"code","target":"vendor_code","transform":"trim"},{"source":"name","target":"vendor_name","transform":"trim"},{"source":"email","target":"email"}]}</textarea>
                </div>
                <div class="md:col-span-2 flex gap-2">
                    <button type="submit" class="bg-purple-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-purple-700">Save Mapping</button>
                    <button type="button" id="runPreview" class="bg-blue-100 text-blue-700 px-4 py-2 rounded-lg text-sm hover:bg-blue-200">Run Preview</button>
                </div>
            </form>

            <div id="syncPreviewBox" class="hidden rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 mb-4"></div>
            <div class="space-y-3 max-h-72 overflow-auto">
                <?php foreach ($syncJobs as $job): ?>
                    <div class="border border-gray-100 rounded-lg p-3">
                        <div class="font-medium text-sm text-gray-800"><?= htmlspecialchars($job['job_key']) ?></div>
                        <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($job['target_entity']) ?> · <?= htmlspecialchars($job['status']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section id="health" class="bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-800">Health</h2>
            <p class="text-sm text-gray-500 mt-1">Authentication state, API status, rate-limit events, and circuit breaker state.</p>
        </div>
        <div class="p-5 space-y-3 max-h-96 overflow-auto">
            <?php foreach ($health as $row): ?>
                <div class="border border-gray-100 rounded-lg p-3">
                    <div class="font-medium text-sm text-gray-800"><?= htmlspecialchars($row['display_name']) ?></div>
                    <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($row['status']) ?> · auth <?= htmlspecialchars($row['auth_status']) ?> · api <?= htmlspecialchars($row['api_status']) ?></div>
                    <div class="text-xs text-gray-400 mt-1">Circuit: <?= htmlspecialchars($row['circuit_state']) ?> · failures: <?= (int) $row['consecutive_failures'] ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    <section id="activity" class="bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-800">Activity</h2>
            <p class="text-sm text-gray-500 mt-1">Safe operational log without secrets or full sensitive payloads.</p>
        </div>
        <div class="divide-y divide-gray-100">
            <?php foreach ($activity as $row): ?>
                <div class="px-5 py-4">
                    <div class="flex justify-between gap-3">
                        <div class="font-medium text-sm text-gray-800"><?= htmlspecialchars($row['capability_key'] ?: $row['connector_key']) ?></div>
                        <div class="text-xs text-gray-400"><?= htmlspecialchars($row['created_at']) ?></div>
                    </div>
                    <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($row['status']) ?> · <?= htmlspecialchars($row['actor_type']) ?> · <?= htmlspecialchars($row['connector_key']) ?></div>
                    <div class="text-xs text-gray-400 mt-1"><?= htmlspecialchars((string) ($row['safe_output_summary'] ?: $row['error_message'] ?: $row['safe_input_summary'])) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-800">F-22 Scope</h2>
            <p class="text-sm text-gray-500 mt-1">Representative connectors delivered in this phase.</p>
        </div>
        <div class="p-5 space-y-3 text-sm text-gray-700">
            <div class="border rounded-lg p-3 bg-gray-50">
                <div class="font-medium">Generic REST Connector</div>
                <div class="text-xs text-gray-500 mt-1">Approved base URL, approved path, approved method, schema-bound inputs, explicit capability registration.</div>
            </div>
            <div class="border rounded-lg p-3 bg-gray-50">
                <div class="font-medium">Outbound Webhook Connector</div>
                <div class="text-xs text-gray-500 mt-1">Allowlisted destinations, HTTPS enforcement, signed payloads, retry policy, SSRF blocking, safe delivery logs.</div>
            </div>
            <div class="border rounded-lg p-3 bg-gray-50">
                <div class="font-medium">Calendar Connector Scaffold</div>
                <div class="text-xs text-gray-500 mt-1">Read-only capability registration reserved for a future provider-backed implementation.</div>
            </div>
            <div id="integrationFeedback" class="hidden rounded-lg border p-3 text-sm"></div>
        </div>
    </section>
</div>

<script>
const apiBase = '<?= url('modules/ai/api/integrations.php') ?>';

function showFeedback(message, ok = true) {
    const box = document.getElementById('integrationFeedback');
    box.className = 'rounded-lg border p-3 text-sm ' + (ok ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800');
    box.textContent = message;
    box.classList.remove('hidden');
}

async function postAction(action, payload) {
    const response = await fetch(apiBase + '?action=' + encodeURIComponent(action), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload || {})
    });
    return response.json();
}

document.getElementById('connectionForm')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const fd = new FormData(event.target);
    const payload = Object.fromEntries(fd.entries());
    payload.enabled_capabilities = fd.getAll('enabled_capabilities[]');
    payload.configuration = {
        approved_base_url: payload.approved_base_url || '',
        timeout_seconds: 20,
        allow_internal_destination: false
    };
    const result = await postAction('save_connection', payload);
    showFeedback(result.success ? 'Connection saved.' : (result.error || 'Unable to save connection.'), !!result.success);
    if (result.success) window.location.reload();
});

document.getElementById('credentialForm')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const payload = Object.fromEntries(new FormData(event.target).entries());
    const result = await postAction('save_credential', payload);
    showFeedback(result.success ? 'Credential rotated.' : (result.error || 'Unable to rotate credential.'), !!result.success);
});

document.getElementById('endpointForm')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const fd = new FormData(event.target);
    const payload = Object.fromEntries(fd.entries());
    payload.allowed_query_params = (payload.allowed_query_params || '').split(',').map(v => v.trim()).filter(Boolean);
    payload.request_schema = { type: 'object', properties: {} };
    payload.output_schema = { type: 'object', properties: { records: { type: 'array' } } };
    const result = await postAction('save_endpoint', payload);
    showFeedback(result.success ? 'Restricted endpoint saved.' : (result.error || 'Unable to save endpoint.'), !!result.success);
});

document.getElementById('webhookForm')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const fd = new FormData(event.target);
    const payload = Object.fromEntries(fd.entries());
    payload.allow_internal_destination = fd.get('allow_internal_destination') === '1';
    payload.payload_schema = { type: 'object', properties: { workflow_id: { type: 'integer' }, status: { type: 'string' } } };
    payload.retry_policy = { max_attempts: 3, retry_delay_seconds: 30, timeout_seconds: 20 };
    const result = await postAction('save_webhook', payload);
    showFeedback(result.success ? 'Webhook saved.' : (result.error || 'Unable to save webhook.'), !!result.success);
    if (result.success) window.location.reload();
});

document.getElementById('mappingForm')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const fd = new FormData(event.target);
    const payload = Object.fromEntries(fd.entries());
    try {
        payload.rules = JSON.parse(payload.rules_json || '{}');
    } catch (error) {
        showFeedback('Rules JSON is invalid.', false);
        return;
    }
    const result = await postAction('save_sync_mapping', payload);
    showFeedback(result.success ? 'Sync mapping saved.' : (result.error || 'Unable to save mapping.'), !!result.success);
    if (result.success) window.location.reload();
});

document.getElementById('runPreview')?.addEventListener('click', async () => {
    const form = document.getElementById('mappingForm');
    const fd = new FormData(form);
    const mappingSavePayload = Object.fromEntries(fd.entries());
    try {
        mappingSavePayload.rules = JSON.parse(mappingSavePayload.rules_json || '{}');
    } catch (error) {
        showFeedback('Rules JSON is invalid.', false);
        return;
    }
    const mapResult = await postAction('save_sync_mapping', mappingSavePayload);
    if (!mapResult.success) {
        showFeedback(mapResult.error || 'Unable to save mapping.', false);
        return;
    }
    const previewResult = await postAction('sync_preview', {
        connection_id: mappingSavePayload.connection_id,
        mapping_id: mapResult.id,
        target_entity: mappingSavePayload.target_entity,
        conflict_strategy: 'manual_review',
        rows: [
            { id: 'SUP-100', code: 'SUP-100', name: 'Atlas Supplies', email: 'ops@atlas.example' },
            { id: 'SUP-200', code: 'SUP-200', name: 'Northwind Traders', email: 'buyers@northwind.example' }
        ]
    });
    if (!previewResult.success) {
        showFeedback(previewResult.error || 'Preview failed.', false);
        return;
    }
    const box = document.getElementById('syncPreviewBox');
    const preview = previewResult.preview || {};
    box.innerHTML = `Preview ready. New: ${preview.new_records || 0}, Updated: ${preview.updated_records || 0}, Conflicts: ${preview.conflicts || 0}, Errors: ${preview.errors || 0}.`;
    box.classList.remove('hidden');
    showFeedback('Sync preview generated.');
});

document.getElementById('sendTestWebhook')?.addEventListener('click', async () => {
    const form = document.getElementById('webhookForm');
    const fd = new FormData(form);
    const payload = Object.fromEntries(fd.entries());
    const result = await postAction('deliver_webhook', {
        webhook_key: payload.webhook_key,
        event_name: payload.event_source || 'workflow.completed',
        payload: { workflow_id: 1, status: 'completed', generated_on: '2026-09-01' }
    });
    showFeedback(result.success ? 'Test webhook delivered.' : (result.error || 'Webhook test failed.'), !!result.success);
});

document.querySelectorAll('.policy-toggle').forEach((el) => {
    el.addEventListener('change', async () => {
        const result = await postAction('update_policy', {
            policy_key: el.dataset.policy,
            enabled: el.checked ? 1 : 0
        });
        showFeedback(result.success ? 'Policy updated.' : (result.error || 'Unable to update policy.'), !!result.success);
    });
});

document.querySelectorAll('.revoke-connection').forEach((el) => {
    el.addEventListener('click', async () => {
        if (!confirm('Revoke this connection and its active credentials?')) {
            return;
        }
        const result = await postAction('revoke_connection', { connection_id: el.dataset.id });
        showFeedback(result.success ? 'Connection revoked.' : (result.error || 'Unable to revoke connection.'), !!result.success);
        if (result.success) window.location.reload();
    });
});

document.getElementById('reloadConnections')?.addEventListener('click', () => window.location.reload());
</script>

<?php require_once AI_APP_ROOT . '/includes/footer.php'; ?>
