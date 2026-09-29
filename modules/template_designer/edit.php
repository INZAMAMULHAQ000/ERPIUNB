<?php
/**
 * Template Designer — Edit/Design Template
 * Visual template designer with section-based editing
 */
$pageTitle = 'Edit Template';
require_once '../../includes/header.php';

if (!hasPermission('template_designer.edit')) {
    die('Access Denied');
}

$db = getDB();
$id = (int)($_GET['id'] ?? 0);

// Load template
$stmt = $db->prepare("SELECT t.*, tc.name AS category_name FROM templates t LEFT JOIN template_categories tc ON t.category_id = tc.id WHERE t.id = ?");
$stmt->execute([$id]);
$template = $stmt->fetch();

if (!$template) {
    flash('error', 'Template not found.', 'error');
    to('index.php');
    exit;
}

// Load latest published version
$stmt = $db->prepare("SELECT * FROM template_versions WHERE template_id = ? AND status = 'published' ORDER BY version_number DESC LIMIT 1");
$stmt->execute([$id]);
$version = $stmt->fetch();

if (!$version) {
    // Fallback: load any version
    $stmt = $db->prepare("SELECT * FROM template_versions WHERE template_id = ? ORDER BY version_number DESC LIMIT 1");
    $stmt->execute([$id]);
    $version = $stmt->fetch();
}

$templateData = $version ? json_decode($version['template_data'], true) : null;

// Get categories
$categories = $db->query("SELECT id, name FROM template_categories ORDER BY sort_order")->fetchAll();

// Get module mappings
$stmt = $db->prepare("SELECT * FROM template_module_mappings WHERE template_id = ?");
$stmt->execute([$id]);
$mappings = $stmt->fetchAll();

// Get assets
$stmt = $db->prepare("SELECT * FROM template_assets WHERE template_id = ? ORDER BY created_at DESC");
$stmt->execute([$id]);
$assets = $stmt->fetchAll();

// Get version history
$stmt = $db->prepare("SELECT tv.*, u.name AS creator_name FROM template_versions tv LEFT JOIN users u ON tv.created_by = u.id WHERE tv.template_id = ? ORDER BY tv.version_number DESC LIMIT 10");
$stmt->execute([$id]);
$versions = $stmt->fetchAll();
?>

<style>
.td-sidebar { width: 280px; min-width: 280px; }
.td-section { scroll-margin-top: 80px; }
.td-component { cursor: pointer; transition: all 0.15s ease; }
.td-component:hover { border-color: #6366f1; box-shadow: 0 0 0 2px rgba(99,102,241,0.15); }
.td-component-placeholder { border: 2px dashed #d1d5db; border-radius: 0.5rem; padding: 2rem; text-align: center; color: #9ca3af; }
.td-dragover { border-color: #6366f1; background: #eef2ff; }
.tabs > input[type="radio"] { display: none; }
.tabs > .tab-content { display: none; }
.tabs > #tab-designer:checked ~ #content-designer,
.tabs > #tab-settings:checked ~ #content-settings,
.tabs > #tab-mappings:checked ~ #content-mappings,
.tabs > #tab-versions:checked ~ #content-versions,
.tabs > #tab-preview:checked ~ #content-preview { display: block; }
.tab-btn { cursor: pointer; }
[contenteditable]:focus { outline: none; border-color: #6366f1; }
.component-item { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 0.75rem; }
.component-item:hover { background: #eef2ff; border-color: #6366f1; }
</style>
<link rel="stylesheet" href="css/visual-editor.css?v=1">

<div class="flex gap-6">
    <!-- Sidebar -->
    <div class="td-sidebar flex-shrink-0">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden sticky" style="top: 5rem;">
            <!-- Template Info -->
            <div class="p-4 border-b border-slate-200">
                <div class="flex items-center justify-between mb-1">
                <h2 class="font-semibold text-slate-800 truncate"><?= sanitize($template['template_name']) ?></h2>
                <span class="text-xs px-2 py-0.5 rounded-full <?= $template['status'] === 'published' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' ?>">
                    <?= ucfirst($template['status']) ?>
                </span>
                </div>
                <p class="text-xs text-slate-400">v<?= (int)$template['version'] ?> &middot; <?= ucwords(str_replace('_', ' ', $template['template_type'])) ?></p>
            </div>

            <!-- Tab Navigation -->
            <div class="tabs">
                <input type="radio" name="tabs" id="tab-designer" checked>
                <input type="radio" name="tabs" id="tab-settings">
                <input type="radio" name="tabs" id="tab-mappings">
                <input type="radio" name="tabs" id="tab-versions">
                <input type="radio" name="tabs" id="tab-preview">

                <div class="flex border-b border-slate-200">
                    <label for="tab-designer" class="tab-btn flex-1 text-center py-2.5 text-xs font-medium text-indigo-600 border-b-2 border-indigo-600">Designer</label>
                    <label for="tab-settings" class="tab-btn flex-1 text-center py-2.5 text-xs font-medium text-slate-500 hover:text-slate-700">Settings</label>
                    <label for="tab-mappings" class="tab-btn flex-1 text-center py-2.5 text-xs font-medium text-slate-500 hover:text-slate-700">Modules</label>
                    <label for="tab-versions" class="tab-btn flex-1 text-center py-2.5 text-xs font-medium text-slate-500 hover:text-slate-700">Versions</label>
                    <label for="tab-preview" class="tab-btn flex-1 text-center py-2.5 text-xs font-medium text-slate-500 hover:text-slate-700">Preview</label>
                </div>

                <!-- ====== TAB: DESIGNER ====== -->
                <div id="content-designer" class="tab-content p-4 space-y-3">
                    <!-- Tool: Add Section -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-2">Sections</label>
                        <div class="space-y-1">
                            <button onclick="addSection('header')" class="component-item w-full text-left text-sm">
                                <i class="fas fa-arrow-up w-5 text-indigo-500"></i> Header
                            </button>
                            <button onclick="addSection('body')" class="component-item w-full text-left text-sm">
                                <i class="fas fa-file-alt w-5 text-indigo-500"></i> Body Section
                            </button>
                            <button onclick="addSection('footer')" class="component-item w-full text-left text-sm">
                                <i class="fas fa-arrow-down w-5 text-indigo-500"></i> Footer
                            </button>
                        </div>
                    </div>

                    <hr class="border-slate-200">

                    <!-- Tool: Components -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-2">Components</label>
                        <div class="space-y-1">
                            <button onclick="addComponent('text')" class="component-item w-full text-left text-sm"><i class="fas fa-font w-5 text-slate-400"></i> Text Block</button>
                            <button onclick="addComponent('table')" class="component-item w-full text-left text-sm"><i class="fas fa-table w-5 text-slate-400"></i> Table</button>
                            <button onclick="addComponent('image')" class="component-item w-full text-left text-sm"><i class="fas fa-image w-5 text-slate-400"></i> Image / Logo</button>
                            <button onclick="addComponent('field')" class="component-item w-full text-left text-sm"><i class="fas fa-tag w-5 text-slate-400"></i> Dynamic Field</button>
                            <button onclick="addComponent('divider')" class="component-item w-full text-left text-sm"><i class="fas fa-minus w-5 text-slate-400"></i> Horizontal Line</button>
                            <button onclick="addComponent('qrcode')" class="component-item w-full text-left text-sm"><i class="fas fa-qrcode w-5 text-slate-400"></i> QR Code</button>
                            <button onclick="addComponent('barcode')" class="component-item w-full text-left text-sm"><i class="fas fa-barcode w-5 text-slate-400"></i> Barcode</button>
                            <button onclick="addComponent('signature')" class="component-item w-full text-left text-sm"><i class="fas fa-pen w-5 text-slate-400"></i> Signature Area</button>
                            <button onclick="addComponent('term')" class="component-item w-full text-left text-sm"><i class="fas fa-file-contract w-5 text-slate-400"></i> Terms & Conditions</button>
                            <button onclick="addComponent('html')" class="component-item w-full text-left text-sm"><i class="fas fa-code w-5 text-slate-400"></i> Custom HTML</button>
                        </div>
                    </div>
                </div>

                <!-- ====== TAB: SETTINGS ====== -->
                <div id="content-settings" class="tab-content p-4 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Template Name</label>
                        <input type="text" id="edit-name" value="<?= sanitize($template['template_name']) ?>"
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Category</label>
                        <select id="edit-category" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                            <option value="">None</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $template['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= sanitize($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Description</label>
                        <textarea id="edit-description" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"><?= sanitize($template['description']) ?></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Page Size</label>
                        <select id="edit-page-size" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                            <option value="A4" <?= ($templateData['page']['size'] ?? 'A4') === 'A4' ? 'selected' : '' ?>>A4</option>
                            <option value="Letter" <?= ($templateData['page']['size'] ?? '') === 'Letter' ? 'selected' : '' ?>>Letter</option>
                            <option value="Legal" <?= ($templateData['page']['size'] ?? '') === 'Legal' ? 'selected' : '' ?>>Legal</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Orientation</label>
                        <select id="edit-orientation" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                            <option value="portrait" <?= ($templateData['page']['orientation'] ?? 'portrait') === 'portrait' ? 'selected' : '' ?>>Portrait</option>
                            <option value="landscape" <?= ($templateData['page']['orientation'] ?? '') === 'landscape' ? 'selected' : '' ?>>Landscape</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Font Family</label>
                        <select id="edit-font" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                            <option value="DejaVu Sans" <?= ($templateData['styles']['font_family'] ?? 'DejaVu Sans') === 'DejaVu Sans' ? 'selected' : '' ?>>DejaVu Sans</option>
                            <option value="DejaVu Serif" <?= ($templateData['styles']['font_family'] ?? '') === 'DejaVu Serif' ? 'selected' : '' ?>>DejaVu Serif</option>
                            <option value="Arial" <?= ($templateData['styles']['font_family'] ?? '') === 'Arial' ? 'selected' : '' ?>>Arial</option>
                            <option value="Times New Roman" <?= ($templateData['styles']['font_family'] ?? '') === 'Times New Roman' ? 'selected' : '' ?>>Times New Roman</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Font Size (px)</label>
                        <input type="number" id="edit-font-size" value="<?= $templateData['styles']['font_size'] ?? 11 ?>" min="8" max="24"
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Primary Color</label>
                        <input type="color" id="edit-primary-color" value="<?= $templateData['styles']['colors']['primary'] ?? '#4f46e5' ?>"
                               class="w-full h-10 border border-slate-300 rounded-lg cursor-pointer">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Table Header BG</label>
                        <input type="color" id="edit-table-bg" value="<?= $templateData['styles']['table_styles']['header_bg'] ?? '#f3f4f6' ?>"
                               class="w-full h-10 border border-slate-300 rounded-lg cursor-pointer">
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="edit-alternate-rows" <?= ($templateData['styles']['table_styles']['alternate_rows'] ?? true) ? 'checked' : '' ?>>
                        <label for="edit-alternate-rows" class="text-sm text-slate-600">Alternate row colors</label>
                    </div>
                </div>

                <!-- ====== TAB: MAPPINGS ====== -->
                <div id="content-mappings" class="tab-content p-4 space-y-4">
                    <p class="text-xs text-slate-500 mb-2">Map this template to modules so they can use it for document generation.</p>
                    <div id="mappings-list" class="space-y-2">
                        <?php foreach ($mappings as $m): ?>
                        <div class="flex items-center justify-between bg-slate-50 rounded-lg p-2 text-sm">
                            <div>
                                <span class="font-medium text-slate-700"><?= sanitize(ucwords(str_replace('_', ' ', $m['module_name']))) ?></span>
                                <?php if ($m['module_action']): ?>
                                <span class="text-slate-400 text-xs ml-1">(<?= sanitize($m['module_action']) ?>)</span>
                                <?php endif; ?>
                                <?php if ($m['is_default']): ?>
                                <span class="ml-1 text-xs text-indigo-600 font-medium">[Default]</span>
                                <?php endif; ?>
                            </div>
                            <button onclick="removeMapping(<?= $m['id'] ?>)" class="text-red-400 hover:text-red-600 p-1">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Module</label>
                        <select id="new-module" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                            <?php foreach (['invoices', 'reports', 'leave_management', 'employees', 'purchase', 'custom', 'attendance'] as $mod): ?>
                            <option value="<?= $mod ?>"><?= ucwords(str_replace('_', ' ', $mod)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Action (optional)</label>
                        <input type="text" id="new-mapping-action" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="e.g., pdf, print, view">
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" id="new-mapping-default"> Set as default
                    </label>
                    <button onclick="addMapping(<?= $id ?>)" class="w-full px-3 py-2 bg-indigo-50 text-indigo-700 rounded-lg hover:bg-indigo-100 text-sm font-medium">
                        <i class="fas fa-plus mr-1"></i> Add Mapping
                    </button>
                </div>

                <!-- ====== TAB: VERSIONS ====== -->
                <div id="content-versions" class="tab-content p-4 space-y-3">
                    <p class="text-xs text-slate-500">Current version: <strong>v<?= (int)$template['version'] ?></strong></p>
                    <div class="space-y-2">
                        <?php foreach ($versions as $v): ?>
                        <div class="flex items-center justify-between p-2 bg-slate-50 rounded-lg text-sm">
                            <div>
                                <span class="font-medium text-slate-700">v<?= (int)$v['version_number'] ?></span>
                                <span class="text-xs ml-1 <?= $v['status'] === 'published' ? 'text-green-600' : 'text-yellow-600' ?>">
                                    (<?= ucfirst($v['status']) ?>)
                                </span>
                            </div>
                            <span class="text-xs text-slate-400"><?= date('M d, H:i', strtotime($v['created_at'])) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ====== TAB: PREVIEW ====== -->
                <div id="content-preview" class="tab-content p-4 space-y-3">
                    <p class="text-xs text-slate-500">Preview how your template will look with sample data.</p>
                    <button onclick="loadPreview(<?= $id ?>)" class="w-full px-3 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">
                        <i class="fas fa-eye mr-1"></i> Generate Preview
                    </button>
                    <button onclick="loadPreview(<?= $id ?>, 'pdf')" class="w-full px-3 py-2 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 text-sm">
                        <i class="fas fa-file-pdf mr-1"></i> Preview as PDF
                    </button>
                    <p class="text-xs text-slate-400 mt-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        PDF preview uses Dompdf. Preview opens in a new tab.
                    </p>
                </div>
            </div>

            <!-- Save Actions -->
            <div class="p-3 border-t border-slate-200 space-y-2">
                <button onclick="saveTemplate(<?= $id ?>)" class="w-full px-4 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium shadow-sm">
                    <i class="fas fa-save mr-2"></i> Save Template
                </button>
                <div class="flex gap-2">
                    <button onclick="publishTemplate(<?= $id ?>)" class="flex-1 px-3 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium">
                        <i class="fas fa-check mr-1"></i> Publish
                    </button>
                    <a href="index.php" class="flex-1 px-3 py-2 text-center border border-slate-300 text-slate-600 rounded-lg hover:bg-slate-50 text-sm">
                        <i class="fas fa-arrow-left mr-1"></i> Back
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ====== MAIN DESIGNER AREA ====== -->
    <div class="flex-1 min-w-0">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <!-- Mode Toggle + Toolbar -->
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">Template Canvas</h3>
                        <p class="text-xs text-slate-400">Design your template layout</p>
                    </div>
                    <div class="ve-mode-toggle">
                        <button class="ve-mode-btn active" data-mode="classic" onclick="switchEditorMode('classic')">Classic</button>
                        <button class="ve-mode-btn" data-mode="visual" onclick="switchEditorMode('visual')">Visual</button>
                    </div>
                </div>
                <div class="flex items-center gap-2 text-xs text-slate-400">
                    <i class="fas fa-arrows-alt"></i> Drag to reorder
                </div>
            </div>

            <!-- Classic Canvas (section-based) -->
            <div id="classic-canvas-content">
                <div id="template-canvas" class="min-h-[600px] border-2 border-dashed border-slate-200 rounded-xl p-4 space-y-4">
                    <?php if ($templateData && !empty($templateData['body']['sections'])): ?>
                        <?php foreach ($templateData['body']['sections'] as $si => $section): ?>
                        <div class="td-section border border-slate-200 rounded-lg p-4 bg-white" data-section="<?= $si ?>">
                            <div class="flex items-center justify-between mb-3 pb-2 border-b border-slate-100">
                                <span class="text-sm font-medium text-slate-600">
                                    <i class="fas fa-<?= $section['type'] === 'header' ? 'arrow-up' : ($section['type'] === 'footer' ? 'arrow-down' : 'file-alt') ?> w-5 text-indigo-500"></i>
                                    <?= ucfirst($section['type']) ?> <?= $section['label'] ? '- ' . sanitize($section['label']) : '' ?>
                                </span>
                                <div class="flex gap-1">
                                    <button onclick="moveSection(<?= $si ?>, -1)" class="p-1 text-slate-400 hover:text-slate-600"><i class="fas fa-chevron-up"></i></button>
                                    <button onclick="moveSection(<?= $si ?>, 1)" class="p-1 text-slate-400 hover:text-slate-600"><i class="fas fa-chevron-down"></i></button>
                                    <button onclick="removeSection(<?= $si ?>)" class="p-1 text-red-400 hover:text-red-600"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                            <div class="space-y-2 min-h-[40px]" data-section-components="<?= $si ?>">
                                <?php if (!empty($section['components'])): ?>
                                    <?php foreach ($section['components'] as $ci => $comp): ?>
                                    <div class="td-component border border-slate-200 rounded-lg p-3 bg-white" data-component="<?= $si ?>-<?= $ci ?>">
                                        <div class="flex items-start justify-between">
                                            <div class="flex items-center gap-2">
                                                <i class="fas fa-grip-vertical text-slate-300 cursor-move"></i>
                                                <span class="text-sm font-medium text-slate-700"><?= ucfirst($comp['type']) ?></span>
                                                <?php if (!empty($comp['label'])): ?>
                                                <span class="text-xs text-slate-400"><?= sanitize($comp['label']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex gap-1">
                                                <button onclick="editComponent(<?= $si ?>, <?= $ci ?>)" class="p-1 text-indigo-400 hover:text-indigo-600"><i class="fas fa-cog"></i></button>
                                                <button onclick="removeComponent(<?= $si ?>, <?= $ci ?>)" class="p-1 text-red-400 hover:text-red-600"><i class="fas fa-times"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="td-component-placeholder" id="canvas-empty">
                            <div class="text-4xl mb-2"><i class="fas fa-paint-brush"></i></div>
                            <p class="font-medium text-slate-500">Start designing your template</p>
                            <p class="text-xs mt-1">Add sections and components from the sidebar</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Visual Canvas (absolute-positioned) -->
            <div id="visual-canvas-wrap" style="display:none">
                <div class="ve-toolbar">
                    <div class="ve-mode-toggle">
                        <button class="ve-mode-btn" data-mode="classic" onclick="switchEditorMode('classic')">Classic</button>
                        <button class="ve-mode-btn active" data-mode="visual" onclick="switchEditorMode('visual')">Visual</button>
                    </div>
                    <button onclick="VisualEditor.undo.undo()" class="px-2 py-1 border rounded text-xs hover:bg-slate-50" title="Undo (Ctrl+Z)"><i class="fas fa-undo"></i></button>
                    <button onclick="VisualEditor.undo.redo()" class="px-2 py-1 border rounded text-xs hover:bg-slate-50" title="Redo (Ctrl+Shift+Z)"><i class="fas fa-redo"></i></button>
                    <span class="flex-1"></span>
                    <button onclick="VisualEditor._deleteSelectedElement()" class="px-2 py-1 border rounded text-xs hover:bg-red-50 text-red-600" title="Delete selected"><i class="fas fa-trash"></i></button>
                </div>
                <div class="ve-layout">
                    <!-- Palette -->
                    <div class="ve-palette">
                        <div class="ve-palette-group">
                            <div class="ve-palette-title">Components</div>
                            <div class="ve-palette-item" draggable="true" data-type="text"><span class="ve-icon"><i class="fas fa-font"></i></span> Text</div>
                            <div class="ve-palette-item" draggable="true" data-type="table"><span class="ve-icon"><i class="fas fa-table"></i></span> Table</div>
                            <div class="ve-palette-item" draggable="true" data-type="image"><span class="ve-icon"><i class="fas fa-image"></i></span> Image / Logo</div>
                            <div class="ve-palette-item" draggable="true" data-type="field"><span class="ve-icon"><i class="fas fa-tag"></i></span> Dynamic Field</div>
                            <div class="ve-palette-item" draggable="true" data-type="divider"><span class="ve-icon"><i class="fas fa-minus"></i></span> Divider</div>
                            <div class="ve-palette-item" draggable="true" data-type="qrcode"><span class="ve-icon"><i class="fas fa-qrcode"></i></span> QR Code</div>
                            <div class="ve-palette-item" draggable="true" data-type="barcode"><span class="ve-icon"><i class="fas fa-barcode"></i></span> Barcode</div>
                            <div class="ve-palette-item" draggable="true" data-type="signature"><span class="ve-icon"><i class="fas fa-pen"></i></span> Signature</div>
                            <div class="ve-palette-item" draggable="true" data-type="term"><span class="ve-icon"><i class="fas fa-file-contract"></i></span> Terms</div>
                            <div class="ve-palette-item" draggable="true" data-type="html"><span class="ve-icon"><i class="fas fa-code"></i></span> Custom HTML</div>
                        </div>
                    </div>
                    <!-- Center: Canvas -->
                    <div class="ve-canvas-wrap">
                        <div id="ve-canvas" class="ve-canvas portrait">
                            <div class="ve-canvas-empty">
                                <div class="ve-icon-big"><i class="fas fa-paint-brush"></i></div>
                                <p>Drag elements from the palette onto the canvas</p>
                            </div>
                        </div>
                    </div>
                    <!-- Right: Properties -->
                    <div id="ve-properties" class="ve-properties">
                        <div class="ve-no-selection">
                            <div class="ve-icon-big"><i class="fas fa-mouse-pointer"></i></div>
                            <p>Select an element to edit properties</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Component Config Modal -->
<div id="component-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center" onclick="if(event.target===this)closeModal()">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 max-h-[80vh] overflow-y-auto">
        <div class="flex items-center justify-between p-4 border-b border-slate-200">
            <h3 class="font-semibold text-slate-800" id="modal-title">Configure Component</h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times text-xl"></i></button>
        </div>
        <div class="p-4 space-y-4" id="modal-body">
            <!-- Dynamic content loaded by JS -->
        </div>
        <div class="flex items-center justify-end gap-2 p-4 border-t border-slate-200">
            <button onclick="closeModal()" class="px-4 py-2 text-slate-600 hover:text-slate-800 text-sm">Cancel</button>
            <button onclick="saveComponentConfig()" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">Apply</button>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
<!-- Visual Editor JS modules -->
<script src="js/visual-editor-core.js?v=1"></script>
<script src="js/visual-editor-dnd.js?v=1"></script>
<script src="js/visual-editor-resize.js?v=1"></script>
<script src="js/visual-editor-bind.js?v=1"></script>
<script src="js/visual-editor-undo.js?v=1"></script>
<script>
// ============================================================
// Template State
// ============================================================
let templateData = <?= json_encode($templateData ?: ['page' => ['size' => 'A4', 'orientation' => 'portrait', 'margins' => ['top' => 20, 'right' => 15, 'bottom' => 20, 'left' => 15]], 'header' => ['enabled' => false, 'content' => [], 'different_first_page' => false], 'footer' => ['enabled' => false, 'content' => [], 'different_first_page' => false], 'body' => ['sections' => []], 'styles' => ['font_family' => 'DejaVu Sans', 'font_size' => 11, 'colors' => ['primary' => '#4f46e5', 'text' => '#1e293b', 'background' => '#ffffff'], 'table_styles' => ['header_bg' => '#f3f4f6', 'border_color' => '#e5e7eb', 'alternate_rows' => true]]]) ?>;
let editingComponent = null; // { section, index }

// ============================================================
// Canvas Rendering
// ============================================================
function renderCanvas() {
    const canvas = document.getElementById('template-canvas');
    if (!templateData.body.sections.length) {
        canvas.innerHTML = `<div class="td-component-placeholder">
            <div class="text-4xl mb-2"><i class="fas fa-paint-brush"></i></div>
            <p class="font-medium text-slate-500">No design sections yet</p>
            <p class="text-xs mt-1">Add sections and components from the sidebar</p>
        </div>`;
        return;
    }
    let html = '';
    templateData.body.sections.forEach((section, si) => {
        html += `<div class="td-section border border-slate-200 rounded-lg p-4 bg-white" data-section="${si}">
            <div class="flex items-center justify-between mb-3 pb-2 border-b border-slate-100">
                <span class="text-sm font-medium text-slate-600">
                    <i class="fas fa-${section.type === 'header' ? 'arrow-up' : (section.type === 'footer' ? 'arrow-down' : 'file-alt')} w-5 text-indigo-500"></i>
                    ${section.type.charAt(0).toUpperCase() + section.type.slice(1)} ${section.label ? ' - ' + escapeHtml(section.label) : ''}
                </span>
                <div class="flex gap-1">
                    <button onclick="moveSection(${si}, -1)" class="p-1 text-slate-400 hover:text-slate-600" title="Move up"><i class="fas fa-chevron-up"></i></button>
                    <button onclick="moveSection(${si}, 1)" class="p-1 text-slate-400 hover:text-slate-600" title="Move down"><i class="fas fa-chevron-down"></i></button>
                    <button onclick="removeSection(${si})" class="p-1 text-red-400 hover:text-red-600" title="Remove"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <div class="space-y-2 min-h-[40px]">`;
        if (section.components && section.components.length) {
            section.components.forEach((comp, ci) => {
                const iconMap = { text: 'font', table: 'table', image: 'image', field: 'tag', divider: 'minus', qrcode: 'qrcode', barcode: 'barcode', signature: 'pen', term: 'file-contract', html: 'code' };
                html += `<div class="td-component border border-slate-200 rounded-lg p-3 bg-white hover:bg-slate-50" data-component="${si}-${ci}">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-${iconMap[comp.type] || 'grip-vertical'} text-slate-400"></i>
                            <span class="text-sm font-medium text-slate-700">${comp.type.charAt(0).toUpperCase() + comp.type.slice(1)}</span>
                            ${comp.label ? `<span class="text-xs text-slate-400">${escapeHtml(comp.label)}</span>` : ''}
                        </div>
                        <div class="flex gap-1">
                            <button onclick="editComponent(${si}, ${ci})" class="p-1 text-indigo-400 hover:text-indigo-600" title="Configure"><i class="fas fa-cog"></i></button>
                            <button onclick="removeComponent(${si}, ${ci})" class="p-1 text-red-400 hover:text-red-600" title="Remove"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                </div>`;
            });
        }
        html += `</div></div>`;
    });
    canvas.innerHTML = html;
}

// ============================================================
// Section Management
// ============================================================
function addSection(type) {
    const label = prompt('Section name (optional):', type.charAt(0).toUpperCase() + type.slice(1));
    templateData.body.sections.push({
        type: type,
        label: label || '',
        components: []
    });
    renderCanvas();
}

function removeSection(index) {
    if (!confirm('Remove this section and all its components?')) return;
    templateData.body.sections.splice(index, 1);
    renderCanvas();
}

function moveSection(index, direction) {
    const newIndex = index + direction;
    if (newIndex < 0 || newIndex >= templateData.body.sections.length) return;
    const item = templateData.body.sections.splice(index, 1)[0];
    templateData.body.sections.splice(newIndex, 0, item);
    renderCanvas();
}

// ============================================================
// Component Management
// ============================================================
function addComponent(type) {
    const compLabels = {
        text: 'Text Block', table: 'Table', image: 'Image / Logo', field: 'Dynamic Field',
        divider: 'Horizontal Line', qrcode: 'QR Code', barcode: 'Barcode',
        signature: 'Signature Area', term: 'Terms & Conditions', html: 'Custom HTML'
    };
    // Find first available section or prompt
    if (!templateData.body.sections.length) {
        alert('Please add a section first (click Header, Body Section, or Footer).');
        return;
    }
    const sectionIdx = prompt('Enter section number (0-' + (templateData.body.sections.length - 1) + '):', '0');
    if (sectionIdx === null || isNaN(sectionIdx) || sectionIdx < 0 || sectionIdx >= templateData.body.sections.length) return;
    const si = parseInt(sectionIdx);

    const comp = { type: type, label: compLabels[type] || type, options: {} };

    // Type-specific defaults
    switch (type) {
        case 'text': comp.content = 'Enter your text here'; break;
        case 'field': comp.placeholder = '{{placeholder}}'; break;
        case 'table': comp.columns = [{ label: 'Column 1', key: 'col1', width: 50 }]; break;
        case 'image': comp.src = ''; comp.width = 150; comp.align = 'left'; break;
        case 'qrcode': comp.data = '{{qrcode_data}}'; comp.size = 80; break;
        case 'barcode': comp.data = '{{barcode_data}}'; comp.format = 'code128'; break;
        case 'signature': comp.label = 'Signature:'; comp.show_label = true; break;
        case 'term': comp.content = 'Terms and conditions text...'; break;
        case 'html': comp.html = '<p>Custom HTML here</p>'; break;
        case 'divider': comp.style = 'solid'; comp.color = '#e5e7eb'; break;
    }
    templateData.body.sections[si].components.push(comp);
    renderCanvas();
}

function removeComponent(si, ci) {
    if (!confirm('Remove this component?')) return;
    templateData.body.sections[si].components.splice(ci, 1);
    renderCanvas();
}

function editComponent(si, ci) {
    editingComponent = { section: si, index: ci };
    const comp = templateData.body.sections[si].components[ci];
    document.getElementById('modal-title').textContent = 'Configure: ' + comp.type.charAt(0).toUpperCase() + comp.type.slice(1);
    const body = document.getElementById('modal-body');
    let html = '';
    const val = (v) => typeof v === 'string' ? v : JSON.stringify(v);

    // Label
    html += `<div><label class="block text-xs font-medium text-slate-500 mb-1">Label</label>
        <input type="text" id="cfg-label" value="${escapeHtml(comp.label || '')}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"></div>`;

    switch (comp.type) {
        case 'text':
            html += `<div><label class="block text-xs font-medium text-slate-500 mb-1">Content</label>
                <textarea id="cfg-content" rows="5" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm font-mono">${escapeHtml(comp.content || '')}</textarea>
                <p class="text-xs text-slate-400 mt-1">Use {{placeholder}} for dynamic values</p></div>`;
            break;
        case 'field':
            html += `<div><label class="block text-xs font-medium text-slate-500 mb-1">Placeholder</label>
                <input type="text" id="cfg-placeholder" value="${escapeHtml(comp.placeholder || '{{}}')}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm font-mono"></div>
            <div><label class="block text-xs font-medium text-slate-500 mb-1">Default Value</label>
                <input type="text" id="cfg-default" value="${escapeHtml(comp.default || '')}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"></div>
            <div class="flex items-center gap-2"><input type="checkbox" id="cfg-hide-empty" ${comp.hide_if_empty ? 'checked' : ''}>
                <label for="cfg-hide-empty" class="text-sm text-slate-600">Hide if empty</label></div>`;
            break;
        case 'image':
            html += `<div><label class="block text-xs font-medium text-slate-500 mb-1">Image URL / Placeholder</label>
                <input type="text" id="cfg-image" value="${escapeHtml(comp.image || '')}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm font-mono"
                       placeholder="{{logo_url}} or uploaded image path"></div>
            <div class="grid grid-cols-2 gap-2">
                <div><label class="block text-xs font-medium text-slate-500 mb-1">Width (px)</label>
                    <input type="number" id="cfg-width" value="${comp.width || 100}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"></div>
                <div><label class="block text-xs font-medium text-slate-500 mb-1">Alignment</label>
                    <select id="cfg-align" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        <option value="left" ${comp.align === 'left' ? 'selected' : ''}>Left</option>
                        <option value="center" ${comp.align === 'center' ? 'selected' : ''}>Center</option>
                        <option value="right" ${comp.align === 'right' ? 'selected' : ''}>Right</option>
                    </select></div>
            </div>`;
            break;
        case 'table':
            html += `<div class="space-y-2"><label class="block text-xs font-medium text-slate-500 mb-1">Columns</label>
                <div id="table-columns">`;
            (comp.columns || [{ label: 'Column 1', key: 'col1', width: 50 }]).forEach((col, i) => {
                html += `<div class="flex gap-2 items-center mb-1">
                    <input type="text" value="${escapeHtml(col.label)}" placeholder="Label" class="flex-1 px-2 py-1 border rounded text-sm" data-col-label="${i}">
                    <input type="text" value="${escapeHtml(col.key)}" placeholder="Key" class="flex-1 px-2 py-1 border rounded text-sm font-mono" data-col-key="${i}">
                    <input type="number" value="${col.width || 50}" placeholder="W" class="w-16 px-2 py-1 border rounded text-sm" data-col-width="${i}">
                    <button onclick="removeColumn(${i})" class="text-red-400 hover:text-red-600 p-1"><i class="fas fa-times"></i></button>
                </div>`;
            });
            html += `<button onclick="addColumn()" class="text-xs text-indigo-600 hover:text-indigo-800"><i class="fas fa-plus mr-1"></i>Add Column</button></div>`;
            break;
        case 'qrcode':
            html += `<div><label class="block text-xs font-medium text-slate-500 mb-1">Data / Placeholder</label>
                <input type="text" id="cfg-qr-data" value="${escapeHtml(comp.data || '{{qrcode_data}}')}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm font-mono"></div>
            <div><label class="block text-xs font-medium text-slate-500 mb-1">Size (px)</label>
                <input type="number" id="cfg-qr-size" value="${comp.size || 80}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"></div>`;
            break;
        case 'barcode':
            html += `<div><label class="block text-xs font-medium text-slate-500 mb-1">Data / Placeholder</label>
                <input type="text" id="cfg-barcode-data" value="${escapeHtml(comp.data || '{{barcode_data}}')}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm font-mono"></div>
            <div><label class="block text-xs font-medium text-slate-500 mb-1">Format</label>
                <select id="cfg-barcode-format" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <option value="code128" ${comp.format === 'code128' ? 'selected' : ''}>Code 128</option>
                    <option value="ean13" ${comp.format === 'ean13' ? 'selected' : ''}>EAN-13</option>
                    <option value="code39" ${comp.format === 'code39' ? 'selected' : ''}>Code 39</option>
                </select></div>`;
            break;
        case 'signature':
            html += `<div><div class="flex items-center gap-2"><input type="checkbox" id="cfg-show-label" ${comp.show_label !== false ? 'checked' : ''}>
                <label for="cfg-show-label" class="text-sm text-slate-600">Show label</label></div>
            <div class="mt-2 text-xs text-slate-400">Signature area will be rendered as a placeholder in the document.</div></div>`;
            break;
        case 'term':
            html += `<div><label class="block text-xs font-medium text-slate-500 mb-1">Content / Placeholder</label>
                <textarea id="cfg-content" rows="5" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm font-mono">${escapeHtml(comp.content || '{{terms_conditions}}')}</textarea></div>`;
            break;
        case 'html':
            html += `<div><label class="block text-xs font-medium text-slate-500 mb-1">Custom HTML</label>
                <textarea id="cfg-custom-html" rows="6" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm font-mono">${escapeHtml(comp.html || '')}</textarea>
                <p class="text-xs text-amber-600 mt-1"><i class="fas fa-exclamation-triangle mr-1"></i>HTML will be sanitized before rendering</p></div>`;
            break;
        case 'divider':
            html += `<div class="grid grid-cols-2 gap-2">
                <div><label class="block text-xs font-medium text-slate-500 mb-1">Style</label>
                    <select id="cfg-divider-style" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        <option value="solid" ${comp.style === 'solid' ? 'selected' : ''}>Solid</option>
                        <option value="dashed" ${comp.style === 'dashed' ? 'selected' : ''}>Dashed</option>
                        <option value="dotted" ${comp.style === 'dotted' ? 'selected' : ''}>Dotted</option>
                    </select></div>
                <div><label class="block text-xs font-medium text-slate-500 mb-1">Color</label>
                    <input type="color" id="cfg-divider-color" value="${comp.color || '#e5e7eb'}" class="w-full h-10 border border-slate-300 rounded-lg cursor-pointer"></div>
            </div>`;
            break;
    }

    body.innerHTML = html;
    document.getElementById('component-modal').classList.remove('hidden');
    document.getElementById('component-modal').classList.add('flex');
}

function saveComponentConfig() {
    if (!editingComponent) return;
    const { section: si, index: ci } = editingComponent;
    const comp = templateData.body.sections[si].components[ci];
    comp.label = document.getElementById('cfg-label')?.value || '';

    switch (comp.type) {
        case 'text': comp.content = document.getElementById('cfg-content')?.value || ''; break;
        case 'field':
            comp.placeholder = document.getElementById('cfg-placeholder')?.value || '{{}}';
            comp.default = document.getElementById('cfg-default')?.value || '';
            comp.hide_if_empty = document.getElementById('cfg-hide-empty')?.checked || false;
            break;
        case 'image':
            comp.image = document.getElementById('cfg-image')?.value || '';
            comp.width = parseInt(document.getElementById('cfg-width')?.value) || 100;
            comp.align = document.getElementById('cfg-align')?.value || 'left';
            break;
        case 'table':
            const cols = [];
            document.querySelectorAll('#table-columns > div').forEach((div) => {
                const label = div.querySelector('[data-col]')?.value || 'Column';
                const key = div.querySelector('[data-col-key]')?.value || 'col';
                const width = parseInt(div.querySelector('[data-col-width]')?.value) || 50;
                cols.push({ label, key, width });
            });
            comp.columns = cols;
            break;
        case 'qrcode':
            comp.data = document.getElementById('cfg-qr-data')?.value || '';
            comp.size = parseInt(document.getElementById('cfg-qr-size')?.value) || 80;
            break;
        case 'barcode':
            comp.data = document.getElementById('cfg-barcode-data')?.value || '';
            comp.format = document.getElementById('cfg-barcode-format')?.value || 'code128';
            break;
        case 'signature':
            comp.show_label = document.getElementById('cfg-show-label')?.checked !== false;
            break;
        case 'term':
            comp.content = document.getElementById('cfg-content')?.value || '';
            break;
        case 'html':
            comp.html = document.getElementById('cfg-custom-html')?.value || '';
            break;
        case 'divider':
            comp.style = document.getElementById('cfg-divider-style')?.value || 'solid';
            comp.color = document.getElementById('cfg-divider-color')?.value || '#e5e7eb';
            break;
    }
    closeModal();
    renderCanvas();
}

function closeModal() {
    document.getElementById('component-modal').classList.add('hidden');
    document.getElementById('component-modal').classList.remove('flex');
    editingComponent = null;
}

// ============================================================
// Save & Publish
// ============================================================
async function saveTemplate(id) {
    const btn = event.target.closest('button');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';

    try {
        // Determine which mode's data to use
        const isVisual = document.getElementById('visual-canvas-wrap')?.style.display !== 'none';
        let saveData = templateData;

        if (isVisual && typeof VisualEditor !== 'undefined') {
            // Get data from visual editor (includes editor_mode, positions, etc.)
            saveData = VisualEditor.getTemplateData();
            saveData.editor_mode = 'visual';
        } else {
            saveData.editor_mode = 'classic';
        }

        // Update settings from UI (works in both modes)
        saveData.page.size = document.getElementById('edit-page-size')?.value || 'A4';
        saveData.page.orientation = document.getElementById('edit-orientation')?.value || 'portrait';
        saveData.styles.font_family = document.getElementById('edit-font')?.value || 'DejaVu Sans';
        saveData.styles.font_size = parseInt(document.getElementById('edit-font-size')?.value) || 11;
        saveData.styles.colors.primary = document.getElementById('edit-primary-color')?.value || '#4f46e5';
        saveData.styles.table_styles.header_bg = document.getElementById('edit-table-bg')?.value || '#f3f4f6';
        saveData.styles.table_styles.alternate_rows = document.getElementById('edit-alternate-rows')?.checked || false;

        const data = {
            template_id: id,
            template_data: saveData,
            template_name: document.getElementById('edit-name')?.value || '',
            category_id: document.getElementById('edit-category')?.value || null,
            description: document.getElementById('edit-description')?.value || '',
            page_size: saveData.page.size,
            page_orientation: saveData.page.orientation,
            csrf_token: '<?= csrfToken() ?>'
        };

        const resp = await fetch('ajax/save_template.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await resp.json();

        if (result.success) {
            showToast('Template saved successfully!');
        } else {
            showToast('Error: ' + (result.error || 'Unknown error'), 'error');
        }
    } catch (e) {
        showToast('Network error: ' + e.message, 'error');
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-save mr-2"></i> Save Template';
}

async function publishTemplate(id) {
    if (!confirm('Publish this template? Published versions are used by modules for document generation.')) return;
    try {
        const resp = await fetch('ajax/save_template.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                template_id: id,
                publish: true,
                csrf_token: '<?= csrfToken() ?>'
            })
        });
        const result = await resp.json();
        if (result.success) {
            showToast('Template published successfully!');
            location.reload();
        } else {
            showToast('Error: ' + (result.error || 'Unknown error'), 'error');
        }
    } catch (e) {
        showToast('Network error: ' + e.message, 'error');
    }
}

function addColumn() {
    const container = document.getElementById('table-columns');
    const i = container.children.length;
    const div = document.createElement('div');
    div.className = 'flex gap-2 items-center mb-1';
    div.innerHTML = `<input type="text" value="" placeholder="Label" class="flex-1 px-2 py-1 border rounded text-xs">
        <input type="text" value="col${i + 1}" placeholder="Key" class="flex-1 px-2 py-1 border rounded text-xs font-mono">
        <input type="number" value="50" placeholder="W" class="w-16 px-2 py-1 border rounded text-sm">
        <button onclick="this.parentElement.remove()" class="text-red-400 hover:text-red-600 p-1"><i class="fas fa-times"></i></button>`;
    container.appendChild(div);
}

function removeColumn(i) {
    const cols = document.getElementById('table-columns');
    if (cols.children.length <= 1) return;
    cols.children[i].remove();
}

// ============================================================
// Module Mappings
// ============================================================
async function addMapping(templateId) {
    const module = document.getElementById('new-module')?.value;
    const action = document.getElementById('new-mapping-action')?.value || null;
    const isDefault = document.getElementById('new-mapping-default')?.checked ? 1 : 0;
    if (!module) return;

    const resp = await fetch('ajax/save_template.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            template_id: templateId,
            add_mapping: { module, action, is_default: isDefault },
            csrf_token: '<?= csrfToken() ?>'
        })
    });
    const result = await resp.json();
    if (result.success) {
        location.reload();
    } else {
        showToast('Error: ' + (result.error || 'Unknown'), 'error');
    }
}

async function removeMapping(id) {
    if (!confirm('Remove this module mapping?')) return;
    const resp = await fetch('ajax/save_template.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            remove_mapping_id: id,
            csrf_token: '<?= csrfToken() ?>'
        })
    });
    const result = await resp.json();
    if (result.success) {
        location.reload();
    } else {
        showToast('Error: ' + (result.error || 'Unknown'), 'error');
    }
}

// ============================================================
// Preview
// ============================================================
function preview(id, format) {
    const url = 'preview.php?id=' + id + (format === 'pdf' ? '&format=pdf' : '');
    window.open(url, '_blank');
}

async function loadPreview(id, format) {
    // First save then preview
    const btn = event?.target?.closest('button');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Generating...'; }
    try {
        await saveTemplate(id);
    } catch (e) {}
    window.open('preview.php?id=' + id + (format === 'pdf' ? '&format=pdf' : ''), '_blank');
    if (btn) { btn.disabled = false; btn.innerHTML = (format === 'pdf' ? '<i class="fas fa-file-pdf mr-1"></i> Preview as PDF' : '<i class="fas fa-eye mr-1"></i> Generate Preview'); }
}

// ============================================================
// Visual Editor Mode Switching
// ============================================================
function switchEditorMode(mode) {
    const classic = document.getElementById('classic-canvas-content');
    const visual = document.getElementById('visual-canvas-wrap');
    const btns = document.querySelectorAll('.ve-mode-btn');

    btns.forEach(b => b.classList.toggle('active', b.dataset.mode === mode));

    if (mode === 'visual') {
        classic.style.display = 'none';
        visual.style.display = 'block';
        // Initialize visual editor if not already done
        if (typeof VisualEditor !== 'undefined' && !VisualEditor._initialized) {
            const editorMode = templateData.editor_mode === 'visual' ? 'visual' : 'classic';
            VisualEditor.init(<?= $id ?>, templateData, 'visual');
            VisualEditor.undo.pushSnapshot();
        } else if (typeof VisualEditor !== 'undefined') {
            VisualEditor.setMode('visual');
            VisualEditor.renderCanvasVisual();
        }
    } else {
        classic.style.display = 'block';
        visual.style.display = 'none';
        if (typeof VisualEditor !== 'undefined') {
            VisualEditor.setMode('classic');
        }
    }
}

// ============================================================
// Utilities
// ============================================================
function showToast(msg, type = 'success') {
    const toast = document.createElement('div');
    toast.className = 'fixed bottom-4 right-4 px-4 py-2 rounded-lg shadow-lg text-sm text-white z-50 transition-all ' +
        (type === 'success' ? 'bg-green-600' : 'bg-red-600');
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

function escapeHtml(str) {
    if (typeof str !== 'string') return str;
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ============================================================
// Visual Editor Auto-Init
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    // If template was saved in visual mode, auto-switch
    if (templateData.editor_mode === 'visual' && typeof VisualEditor !== 'undefined') {
        switchEditorMode('visual');
    }
});
</script>