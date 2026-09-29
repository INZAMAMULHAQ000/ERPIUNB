/**
 * Visual Editor — Properties Panel & Data Binding
 * Shows component properties when selected, with placeholder picker
 */
VisualEditor.bind = (() => {
    'use strict';

    let panelEl = null;

    function init() {
        panelEl = document.getElementById('ve-properties');

        VisualEditor.on('onSelect', onElementSelected);
        VisualEditor.on('onDeselect', onElementDeselected);
    }

    function onElementSelected(sel) {
        if (!panelEl) return;
        const comp = VisualEditor.getComponent(sel.sectionIdx, sel.compIdx);
        if (!comp) return;

        let html = buildPropertiesHTML(comp, sel);

        // Add data binding section
        html += buildDataBindingSection(comp, sel);

        panelEl.innerHTML = html;
        attachPropertyListeners(sel);
        loadPlaceholders(sel);
    }

    function onElementDeselected() {
        if (!panelEl) return;
        panelEl.innerHTML = `<div class="ve-no-selection">
            <div class="ve-icon-big"><i class="fas fa-mouse-pointer"></i></div>
            <p>Click an element on the canvas to edit its properties</p>
        </div>`;
    }

    function buildPropertiesHTML(comp, sel) {
        const typeLabels = {
            text: 'Text Block', table: 'Table', image: 'Image / Logo', field: 'Dynamic Field',
            divider: 'Horizontal Line', qrcode: 'QR Code', barcode: 'Barcode',
            signature: 'Signature Area', term: 'Terms & Conditions', html: 'Custom HTML'
        };

        let html = `<div class="ve-properties-title">${typeLabels[comp.type] || comp.type}</div>`;

        // Label (all types)
        html += buildInput('Label', 'prop-label', comp.label || '');

        // Position
        const pos = comp.position || { x: 20, y: 20, w: 200, h: 40 };
        html += `<div class="ve-pos-row">
            ${buildInput('X', 'prop-x', pos.x, 'number')}
            ${buildInput('Y', 'prop-y', pos.y, 'number')}
            ${buildInput('W', 'prop-w', pos.w, 'number')}
            ${buildInput('H', 'prop-h', pos.h, 'number')}
        </div>`;

        // Type-specific fields
        switch (comp.type) {
            case 'text':
                html += buildTextarea('Content', 'prop-content', comp.content || '');
                html += `<div class="ve-pos-row">
                    ${buildSelect('Font Size', 'prop-font-size', (comp.styles && comp.styles.fontSize) || 14, {
                        '10': '10px', '12': '12px', '14': '14px', '16': '16px', '18': '18px', '20': '20px', '24': '24px', '28': '28px', '32': '32px'
                    })}
                    ${buildSelect('Align', 'prop-align', (comp.styles && comp.styles.align) || 'left', {
                        'left': 'Left', 'center': 'Center', 'right': 'Right'
                    })}
                </div>`;
                html += `<label class="ve-prop-checkbox"><input type="checkbox" id="prop-bold" ${(comp.styles && comp.styles.bold) ? 'checked' : ''}> <span>Bold</span></label>`;
                html += buildInput('Color', 'prop-color', (comp.styles && comp.styles.color) || '#1e293b', 'color');
                break;

            case 'field':
                html += buildInput('Placeholder', 'prop-placeholder', comp.placeholder || '{{}}');
                html += buildInput('Default Value', 'prop-default', comp.default || '');
                html += `<label class="ve-prop-checkbox"><input type="checkbox" id="prop-hide-empty" ${comp.hide_if_empty ? 'checked' : ''}> <span>Hide if empty</span></label>`;
                break;

            case 'table':
                html += buildInput('Data Key', 'prop-data-key', comp.data_key || 'report_data');
                html += buildTableColumnsEditor(comp.columns || []);
                break;

            case 'image':
                html += buildInput('Source URL', 'prop-image-src', comp.src || comp.image || '');
                html += `<div class="ve-pos-row">
                    ${buildInput('Width (px)', 'prop-img-width', comp.width || 150, 'number')}
                    ${buildSelect('Align', 'prop-img-align', comp.align || 'left', {'left': 'Left', 'center': 'Center', 'right': 'Right'})}
                </div>`;
                break;

            case 'divider':
                html += buildSelect('Style', 'prop-divider-style', comp.style || 'solid', {
                    'solid': 'Solid', 'dashed': 'Dashed', 'dotted': 'Dotted'
                });
                html += buildInput('Color', 'prop-divider-color', comp.color || '#e5e7eb', 'color');
                break;

            case 'qrcode':
                html += buildInput('Data', 'prop-qr-data', comp.data || '{{qrcode_data}}');
                html += buildInput('Size (px)', 'prop-qr-size', comp.size || 80, 'number');
                break;

            case 'barcode':
                html += buildInput('Data', 'prop-barcode-data', comp.data || '{{barcode_data}}');
                html += buildSelect('Format', 'prop-barcode-format', comp.format || 'code128', {
                    'code128': 'Code 128', 'ean13': 'EAN-13', 'code39': 'Code 39'
                });
                break;

            case 'signature':
                html += buildInput('Label', 'prop-sig-label', comp.label || 'Signature:');
                html += `<label class="ve-prop-checkbox"><input type="checkbox" id="prop-sig-show-label" ${comp.show_label !== false ? 'checked' : ''}> <span>Show label</span></label>`;
                break;

            case 'term':
                html += buildTextarea('Content', 'prop-term-content', comp.content || '');
                break;

            case 'html':
                html += buildTextarea('HTML', 'prop-html-content', comp.html || '');
                break;
        }

        return html;
    }

    function buildDataBindingSection(comp, sel) {
        let html = `<div class="ve-bind-section">
            <div class="ve-prop-label">Data Binding</div>
            <div style="margin-bottom:8px;">
                <select id="prop-data-bind" class="ve-prop-select">
                    <option value="">— Not bound —</option>
                </select>
            </div>
            <div id="placeholder-groups">
                <div style="color:#9ca3af; font-size:11px;">Select a module mapping to see available fields...</div>
            </div>
        </div>`;

        // Add delete button
        html += `<div style="margin-top:12px; padding-top:12px; border-top:1px solid #e5e7eb;">
            <button onclick="VisualEditor._deleteSelectedElement()" class="ve-prop-select" style="color:#dc2626; border-color:#fca5a5;">
                <i class="fas fa-trash"></i> Delete Element
            </button>
        </div>`;

        return html;
    }

    function loadPlaceholders(sel) {
        const comp = VisualEditor.getComponent(sel.sectionIdx, sel.compIdx);
        const bindSelect = document.getElementById('prop-data-bind');
        const groupsEl = document.getElementById('placeholder-groups');
        if (!bindSelect || !groupsEl) return;

        // Try to determine module from mappings
        const module = guessModule();
        const action = '';

        // Show loading
        groupsEl.innerHTML = '<div style="color:#9ca3af; font-size:11px;">Loading fields...</div>';

        // Fetch placeholders from existing registry via AJAX
        fetch('ajax/load_placeholders.php?module=' + encodeURIComponent(module) + '&action=' + encodeURIComponent(action))
            .then(r => r.json())
            .then(data => {
                groupsEl.innerHTML = '';
                let hasItems = false;

                // Build placeholder groups
                const allGroups = data.groups || [];

                // Also try getting from placeholder_registry via grouped format
                const bindVal = comp.data_bind || '';

                // Clear and rebuild select
                bindSelect.innerHTML = '<option value="">— Not bound —</option>';

                allGroups.forEach(group => {
                    if (!group.items || !group.items.length) return;
                    hasItems = true;

                    // Add to dropdown as optgroup
                    const optgroup = document.createElement('optgroup');
                    optgroup.label = group.label;

                    // Add to groups display
                    let groupHtml = `<div class="ve-bind-group">
                        <div class="ve-bind-group-title">${escapeHtml(group.label)}</div>`;

                    group.items.forEach(item => {
                        const opt = document.createElement('option');
                        opt.value = item.key;
                        opt.textContent = item.label;
                        if (item.key === bindVal) opt.selected = true;
                        optgroup.appendChild(opt);

                        groupHtml += `<div class="ve-bind-item" data-key="${escapeHtml(item.key)}" onclick="VisualEditor.bind._applyBinding('${escapeHtml(item.key)}')">
                            <span>${escapeHtml(item.label)}</span>
                            <span class="ve-bind-key">{{${escapeHtml(item.key)}}}</span>
                        </div>`;
                    });

                    groupHtml += `</div>`;
                    groupsEl.innerHTML += groupHtml;
                    bindSelect.appendChild(optgroup);
                });

                if (!hasItems) {
                    groupsEl.innerHTML = '<div style="color:#9ca3af; font-size:11px;">No placeholder fields found for this module</div>';
                    groupsEl.innerHTML += `<div style="font-size:11px; color:#6b7280; margin-top:8px;">
                        <i class="fas fa-info-circle"></i>
                        Map this template to a module (Modules tab) to see available fields.
                    </div>`;
                }

                // Bind dropdown change
                bindSelect.onchange = () => {
                    if (bindSelect.value) {
                        applyBinding(bindSelect.value, sel);
                    }
                };
            })
            .catch(() => {
                groupsEl.innerHTML = '<div style="color:#9ca3af; font-size:11px;">Could not load fields. Map a module first.</div>';
            });
    }

    function guessModule() {
        // Check if template already has module mappings in the DOM
        const mappingsEl = document.querySelector('#mappings-list');
        if (mappingsEl) {
            const mapItem = mappingsEl.querySelector('.font-medium');
            if (mapItem) return mapItem.textContent.trim().toLowerCase();
        }
        // Default based on template type
        const typeEl = document.querySelector('[data-template-type]');
        if (typeEl) return typeEl.dataset.templateType;
        return 'reports';
    }

    function applyBinding(key, sel) {
        const comp = VisualEditor.getComponent(sel.sectionIdx, sel.compIdx);
        if (!comp) return;

        comp.data_bind = key;

        // For text and field types, update content
        if (comp.type === 'text') {
            comp.content = '{{' + key + '}}';
        } else if (comp.type === 'field') {
            comp.placeholder = '{{' + key + '}}';
        } else if (comp.type === 'image') {
            comp.src = '{{' + key + '}}';
        } else if (comp.type === 'table') {
            comp.data_key = key;
        }

        VisualEditor.markDirty();
        VisualEditor.selectElement(sel.sectionIdx, sel.compIdx); // Re-render properties
        VisualEditor.renderCanvasVisual();
    }

    function attachPropertyListeners(sel) {
        // General change handler for all inputs
        const applyChanges = () => {
            const comp = VisualEditor.getComponent(sel.sectionIdx, sel.compIdx);
            if (!comp) return;
            readPropertiesToComponent(comp);
            VisualEditor.markDirty();
        };

        // Attach change events
        document.querySelectorAll('.ve-prop-input, .ve-prop-select, .ve-prop-textarea').forEach(el => {
            el.addEventListener('change', applyChanges);
            el.addEventListener('blur', applyChanges);
        });

        // Checkboxes
        document.querySelectorAll('.ve-prop-checkbox input[type="checkbox"]').forEach(el => {
            el.addEventListener('change', applyChanges);
        });

        // Color inputs
        document.querySelectorAll('input[type="color"]').forEach(el => {
            el.addEventListener('input', applyChanges);
        });

        // Table column editors
        document.querySelectorAll('.table-col-label, .table-col-key, .table-col-width').forEach(el => {
            el.addEventListener('change', applyChanges);
        });
    }

    function readPropertiesToComponent(comp) {
        // Label
        const labelEl = document.getElementById('prop-label');
        if (labelEl) comp.label = labelEl.value;

        // Position
        ['x','y','w','h'].forEach(dim => {
            const el = document.getElementById('prop-' + dim);
            if (el) {
                if (!comp.position) comp.position = {};
                comp.position[dim] = parseInt(el.value) || 0;
            }
        });

        // Type-specific
        switch (comp.type) {
            case 'text': {
                const content = document.getElementById('prop-content');
                if (content) comp.content = content.value;
                if (!comp.styles) comp.styles = {};
                const fs = document.getElementById('prop-font-size');
                if (fs) comp.styles.fontSize = parseInt(fs.value);
                const align = document.getElementById('prop-align');
                if (align) comp.styles.align = align.value;
                const bold = document.getElementById('prop-bold');
                if (bold) comp.styles.bold = bold.checked;
                const color = document.getElementById('prop-color');
                if (color) comp.styles.color = color.value;
                break;
            }
            case 'field': {
                const ph = document.getElementById('prop-placeholder');
                if (ph) comp.placeholder = ph.value;
                const def = document.getElementById('prop-default');
                if (def) comp.default = def.value;
                const hide = document.getElementById('prop-hide-empty');
                if (hide) comp.hide_if_empty = hide.checked;
                break;
            }
            case 'table': {
                const dk = document.getElementById('prop-data-key');
                if (dk) comp.data_key = dk.value;
                // Read columns
                const cols = [];
                document.querySelectorAll('.table-col-row').forEach(row => {
                    const label = row.querySelector('.table-col-label')?.value || '';
                    const key = row.querySelector('.table-col-key')?.value || '';
                    const width = parseInt(row.querySelector('.table-col-width')?.value) || 20;
                    if (key) cols.push({ label, key, width });
                });
                if (cols.length) comp.columns = cols;
                break;
            }
            case 'image': {
                const src = document.getElementById('prop-image-src');
                if (src) comp.src = comp.image = src.value;
                const w = document.getElementById('prop-img-width');
                if (w) comp.width = parseInt(w.value);
                const a = document.getElementById('prop-img-align');
                if (a) comp.align = a.value;
                break;
            }
            case 'divider': {
                const style = document.getElementById('prop-divider-style');
                if (style) comp.style = style.value;
                const color = document.getElementById('prop-divider-color');
                if (color) comp.color = color.value;
                break;
            }
            case 'qrcode': {
                const data = document.getElementById('prop-qr-data');
                if (data) comp.data = data.value;
                const sz = document.getElementById('prop-qr-size');
                if (sz) comp.size = parseInt(sz.value);
                break;
            }
            case 'barcode': {
                const data = document.getElementById('prop-barcode-data');
                if (data) comp.data = data.value;
                const fmt = document.getElementById('prop-barcode-format');
                if (fmt) comp.format = fmt.value;
                break;
            }
            case 'signature': {
                const label = document.getElementById('prop-sig-label');
                if (label) comp.label = label.value;
                const show = document.getElementById('prop-sig-show-label');
                if (show) comp.show_label = show.checked;
                break;
            }
            case 'term': {
                const content = document.getElementById('prop-term-content');
                if (content) comp.content = content.value;
                break;
            }
            case 'html': {
                const html = document.getElementById('prop-html-content');
                if (html) comp.html = html.value;
                break;
            }
        }
    }

    // ── Table Columns Editor ──
    function buildTableColumnsEditor(columns) {
        let html = `<div class="ve-prop-group">
            <div class="ve-prop-label">Table Columns</div>
            <div id="table-columns-editor">`;
        columns.forEach((col, i) => {
            html += `<div class="table-col-row" style="display:flex; gap:4px; margin-bottom:4px;">
                <input type="text" class="table-col-label ve-prop-input" value="${escapeHtml(col.label)}" placeholder="Label" style="flex:2; min-width:0;">
                <input type="text" class="table-col-key ve-prop-input" value="${escapeHtml(col.key)}" placeholder="Key" style="flex:1; min-width:0; font-family:monospace;">
                <input type="number" class="table-col-width ve-prop-input" value="${col.width || 20}" placeholder="W" style="width:50px;">
            </div>`;
        });
        html += `</div>
            <button type="button" onclick="VisualEditor.bind._addTableColumn()" style="font-size:11px; color:#6366f1; cursor:pointer; border:none; background:none; padding:4px 0;">
                <i class="fas fa-plus"></i> Add Column
            </button>
        </div>`;
        return html;
    }

    // ── Utility ──
    function buildInput(label, id, value, type) {
        type = type || 'text';
        const isColor = type === 'color';
        return `<div class="ve-prop-group">
            <label class="ve-prop-label" for="${id}">${label}</label>
            <input type="${type}" id="${id}" value="${escapeHtml(String(value))}" class="${isColor ? 've-prop-color' : 've-prop-input'}">
        </div>`;
    }

    function buildTextarea(label, id, value) {
        return `<div class="ve-prop-group">
            <label class="ve-prop-label" for="${id}">${label}</label>
            <textarea id="${id}" class="ve-prop-textarea">${escapeHtml(value)}</textarea>
        </div>`;
    }

    function buildSelect(label, id, value, options) {
        let opts = Object.entries(options).map(([k, v]) =>
            `<option value="${escapeHtml(k)}" ${String(k) === String(value) ? 'selected' : ''}>${escapeHtml(v)}</option>`
        ).join('');
        return `<div class="ve-prop-group">
            <label class="ve-prop-label" for="${id}">${label}</label>
            <select id="${id}" class="ve-prop-select">${opts}</select>
        </div>`;
    }

    function escapeHtml(str) {
        if (typeof str !== 'string') return str || '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ── Public helpers (called from inline onclick) ──
    function _addTableColumn() {
        const editor = document.getElementById('table-columns-editor');
        if (!editor) return;
        const div = document.createElement('div');
        div.className = 'table-col-row';
        div.style.cssText = 'display:flex; gap:4px; margin-bottom:4px;';
        div.innerHTML = `
            <input type="text" class="table-col-label ve-prop-input" placeholder="Label" style="flex:2; min-width:0;">
            <input type="text" class="table-col-key ve-prop-input" placeholder="Key" style="flex:1; min-width:0; font-family:monospace;">
            <input type="number" class="table-col-width ve-prop-input" value="20" placeholder="W" style="width:50px;">
        `;
        editor.appendChild(div);
    }

    function _applyBinding(key) {
        const sel = VisualEditor.getSelected();
        if (!sel) return;
        applyBinding(key, sel);
    }

    return { init, _addTableColumn, _applyBinding };
})();

// Delete element handler (called from properties panel)
VisualEditor._deleteSelectedElement = function() {
    const sel = VisualEditor.getSelected();
    if (!sel) return;
    if (!confirm('Remove this element from the template?')) return;
    VisualEditor.removeComponent(sel.sectionIdx, sel.compIdx);
    document.getElementById('properties-panel').innerHTML = '';
};
