/**
 * Visual Editor — Core Engine
 * Canvas lifecycle, templateData state, rendering, selection, mode toggle
 */
const VisualEditor = (() => {
    'use strict';

    // ── State ──
    let templateData = null;
    let selectedElement = null;  // { sectionIdx, compIdx }
    let editorMode = 'classic';  // 'classic' | 'visual'
    let canvasEl = null;
    let isDirty = false;
    let templateId = 0;

    // Callbacks for submodules
    const _callbacks = { onSelect: [], onDeselect: [], onChange: [], onRender: [] };

    // ── Init ──
    function init(id, data, mode) {
        templateId = id;
        templateData = (typeof data === 'string' ? JSON.parse(data) : data) || getDefaultData();
        editorMode = mode || templateData.editor_mode || 'classic';
        canvasEl = document.getElementById(editorMode === 'visual' ? 've-canvas' : 'template-canvas');

        if (editorMode === 'visual') {
            initVisualMode();
        }

        // Hook into existing save
        const origSave = window.saveTemplate;
        if (origSave) {
            window.saveTemplate = function(id) {
                syncDataToForm();
                return origSave(id);
            };
        }
    }

    function getDefaultData() {
        return {
            page: { size: 'A4', orientation: 'portrait', margins: { top: 20, right: 15, bottom: 20, left: 15 } },
            header: { enabled: true, height: 80, content: [] },
            body: { sections: [{ type: 'section', label: 'Body', components: [] }] },
            footer: { enabled: true, height: 60, content: [] },
            styles: { font_family: 'DejaVu Sans', font_size: 11, colors: { primary: '#4f46e5', text: '#1e293b', background: '#ffffff' }, table_styles: { header_bg: '#f3f4f6', border_color: '#e5e7eb', alternate_rows: true } },
            editor_mode: 'visual'
        };
    }

    // ── Mode Toggle ──
    function setMode(mode) {
        if (mode === editorMode) return;
        editorMode = mode;
        templateData.editor_mode = mode;
        isDirty = true;

        // Switch canvas element reference for the active mode
        canvasEl = document.getElementById(mode === 'visual' ? 've-canvas' : 'template-canvas');

        document.querySelectorAll('.ve-mode-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.mode === mode);
        });

        if (mode === 'visual') {
            initVisualMode();
        } else {
            exitVisualMode();
        }
        trigger('onChange');
    }

    function initVisualMode() {
        // Hide classic canvas content, show visual layout
        const classicContent = document.getElementById('classic-canvas-content');
        const visualWrap = document.getElementById('visual-canvas-wrap');
        if (classicContent) classicContent.style.display = 'none';
        if (visualWrap) visualWrap.style.display = 'block';

        renderCanvasVisual();
    }

    function exitVisualMode() {
        const classicContent = document.getElementById('classic-canvas-content');
        const visualWrap = document.getElementById('visual-canvas-wrap');
        if (classicContent) classicContent.style.display = '';
        if (visualWrap) visualWrap.style.display = 'none';
        deselectElement();
    }

    // ── Visual Canvas Rendering ──
    function renderCanvasVisual() {
        if (!canvasEl) return;
        const canvas = canvasEl;

        // Set orientation class
        const orient = (templateData.page && templateData.page.orientation) || 'portrait';
        canvas.className = 've-canvas ' + orient;

        // Build canvas HTML
        let html = '';

        // Render header elements
        if (templateData.header && templateData.header.enabled && templateData.header.content) {
            templateData.header.content.forEach((comp, ci) => {
                html += renderElement('header', ci, comp);
            });
        }

        // Render body elements
        if (templateData.body && templateData.body.sections) {
            templateData.body.sections.forEach((section, si) => {
                if (section.components) {
                    section.components.forEach((comp, ci) => {
                        html += renderElement(si, ci, comp);
                    });
                }
            });
        }

        // Render footer elements
        if (templateData.footer && templateData.footer.enabled && templateData.footer.content) {
            templateData.footer.content.forEach((comp, ci) => {
                html += renderElement('footer', ci, comp);
            });
        }

        // Empty state
        if (!hasAnyElements()) {
            html = `<div class="ve-canvas-empty">
                <div class="ve-icon-big"><i class="fas fa-paint-brush"></i></div>
                <p>Drag components from the palette onto this canvas</p>
            </div>`;
        }

        canvas.innerHTML = html;

        // Attach event listeners to elements
        canvas.querySelectorAll('.ve-element').forEach(el => {
            el.addEventListener('mousedown', onElementMouseDown);
        });

        // Canvas click to deselect
        canvas.addEventListener('mousedown', onCanvasMouseDown);

        trigger('onRender');
    }

    function hasAnyElements() {
        if (templateData.header && templateData.header.enabled && templateData.header.content && templateData.header.content.length) return true;
        if (templateData.footer && templateData.footer.enabled && templateData.footer.content && templateData.footer.content.length) return true;
        if (templateData.body && templateData.body.sections) {
            for (const s of templateData.body.sections) {
                if (s.components && s.components.length) return true;
            }
        }
        return false;
    }

    function renderElement(sectionIdx, compIdx, comp) {
        const pos = comp.position || { x: 20, y: 20, w: 200, h: 40 };
        const isSelected = selectedElement && selectedElement.sectionIdx === sectionIdx && selectedElement.compIdx === compIdx;
        const iconMap = { text: 'font', table: 'table', image: 'image', field: 'tag', divider: 'minus', qrcode: 'qrcode', barcode: 'barcode', signature: 'pen', term: 'file-contract', html: 'code' };

        let contentHtml = getElementPreview(comp);

        return `<div class="ve-element${isSelected ? ' selected' : ''}" data-section="${sectionIdx}" data-comp="${compIdx}"
                 style="left:${pos.x}px; top:${pos.y}px; width:${pos.w}px; height:${pos.h}px;">
            <div class="ve-element-badge"><i class="fas fa-${iconMap[comp.type] || 'cube'}"></i></div>
            <div class="ve-content">${contentHtml}</div>
            <div class="ve-resize-handle nw"></div>
            <div class="ve-resize-handle n"></div>
            <div class="ve-resize-handle ne"></div>
            <div class="ve-resize-handle w"></div>
            <div class="ve-resize-handle e"></div>
            <div class="ve-resize-handle sw"></div>
            <div class="ve-resize-handle s"></div>
            <div class="ve-resize-handle se"></div>
        </div>`;
    }

    function getElementPreview(comp) {
        switch (comp.type) {
            case 'text':
                return `<span style="font-size:${(comp.styles && comp.styles.fontSize) || 14}px; font-weight:${(comp.styles && comp.styles.bold) ? 'bold' : 'normal'}; color:${(comp.styles && comp.styles.color) || '#1e293b'}; text-align:${(comp.styles && comp.styles.align) || 'left'}">${escapeHtml(comp.content || 'Text')}</span>`;
            case 'field':
                return `<span style="color:#6366f1; font-weight:500; background:#eef2ff; padding:2px 8px; border-radius:4px;">${escapeHtml(comp.placeholder || '{{field}}')}</span>`;
            case 'image':
                return `<div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:#f9fafb; border:1px dashed #d1d5db; border-radius:4px; color:#9ca3af; font-size:11px;"><i class="fas fa-image" style="margin-right:6px;"></i>${escapeHtml(comp.label || 'Image')}</div>`;
            case 'table': {
                const cols = comp.columns || [{ label: 'Col 1', key: 'col1' }];
                let th = cols.map(c => `<th>${escapeHtml(c.label)}</th>`).join('');
                let td = cols.map(c => `<td>—</td>`).join('');
                return `<table style="font-size:10px;"><thead><tr>${th}</tr></thead><tbody><tr>${td}</tr><tr>${td}</tr></tbody></table>`;
            }
            case 'divider':
                return `<hr style="border:0; border-top:2px solid ${comp.color || '#e5e7eb'}; ${comp.style === 'dashed' ? 'border-top-style:dashed;' : comp.style === 'dotted' ? 'border-top-style:dotted;' : ''}">`;
            case 'qrcode':
                return `<div style="display:flex; align-items:center; justify-content:center; height:100%; color:#6366f1;"><i class="fas fa-qrcode" style="font-size:32px;"></i></div>`;
            case 'barcode':
                return `<div style="display:flex; align-items:center; justify-content:center; height:100%; color:#6366f1;"><i class="fas fa-barcode" style="font-size:32px;"></i></div>`;
            case 'signature':
                return `<div style="border-bottom:1px solid #9ca3af; width:80%; margin:auto; padding-top:20px; text-align:center; color:#9ca3af; font-size:10px;">${comp.show_label !== false && comp.label ? escapeHtml(comp.label) : 'Signature'}</div>`;
            case 'term':
                return `<div style="font-size:9px; color:#6b7280; overflow:hidden;">${escapeHtml((comp.content || 'Terms & Conditions').substring(0, 100))}${(comp.content || '').length > 100 ? '...' : ''}</div>`;
            case 'html':
                return `<div style="font-size:10px; color:#6b7280; font-family:monospace;">&lt;html&gt;...</div>`;
            default:
                return `<span>${escapeHtml(comp.type)}</span>`;
        }
    }

    // ── Element Selection ──
    function onElementMouseDown(e) {
        if (e.button !== 0) return;
        const el = e.currentTarget;
        const sectionIdx = el.dataset.section;
        const compIdx = parseInt(el.dataset.comp);

        // Convert 'header'/'footer' string to index
        const si = sectionIdx === 'header' ? 'header' : sectionIdx === 'footer' ? 'footer' : parseInt(sectionIdx);
        selectElement(si, compIdx);
        e.stopPropagation();
    }

    function onCanvasMouseDown(e) {
        if (e.target === canvasEl || e.target.classList.contains('ve-canvas') || e.target.classList.contains('ve-canvas-empty')) {
            deselectElement();
        }
    }

    function selectElement(sectionIdx, compIdx) {
        if (selectedElement && selectedElement.sectionIdx === sectionIdx && selectedElement.compIdx === compIdx) return;
        selectedElement = { sectionIdx, compIdx };
        updateElementSelection();
        trigger('onSelect', selectedElement);
    }

    function deselectElement() {
        if (!selectedElement) return;
        const prev = selectedElement;
        selectedElement = null;
        updateElementSelection();
        trigger('onDeselect', prev);
    }

    function updateElementSelection() {
        canvasEl.querySelectorAll('.ve-element').forEach(el => {
            const match = selectedElement &&
                el.dataset.section == selectedElement.sectionIdx &&
                parseInt(el.dataset.comp) === selectedElement.compIdx;
            el.classList.toggle('selected', !!match);
        });
    }

    // ── Get/Set Component Data ──
    function getComponent(sectionIdx, compIdx) {
        const section = getSection(sectionIdx);
        if (!section || !section.components || !section.components[compIdx]) return null;
        return section.components[compIdx];
    }

    function updateComponent(sectionIdx, compIdx, updates) {
        const comp = getComponent(sectionIdx, compIdx);
        if (!comp) return;
        Object.assign(comp, updates);
        isDirty = true;
        renderCanvasVisual();
        trigger('onChange');
    }

    function removeComponent(sectionIdx, compIdx) {
        const section = getSection(sectionIdx);
        if (!section || !section.components) return;
        section.components.splice(compIdx, 1);
        isDirty = true;
        if (selectedElement && selectedElement.sectionIdx === sectionIdx && selectedElement.compIdx === compIdx) {
            deselectElement();
        }
        renderCanvasVisual();
        trigger('onChange');
    }

    function addComponent(sectionIdx, comp) {
        const section = getSection(sectionIdx);
        if (!section) return;
        if (!section.components) section.components = [];
        // Auto-position: stack below last element
        const lastComp = section.components[section.components.length - 1];
        const lastPos = lastComp && lastComp.position ? lastComp.position : { y: 20 };
        comp.position = comp.position || { x: 20, y: lastPos.y + 60, w: 200, h: 40 };
        section.components.push(comp);
        isDirty = true;
        renderCanvasVisual();
        trigger('onChange');
        return comp;
    }

    function getSection(sectionIdx) {
        if (sectionIdx === 'header') return templateData.header;
        if (sectionIdx === 'footer') return templateData.footer;
        if (templateData.body && templateData.body.sections && templateData.body.sections[sectionIdx]) {
            return templateData.body.sections[sectionIdx];
        }
        return null;
    }

    // ── Sync data back to form fields before save ──
    function syncDataToForm() {
        // Update editor mode
        templateData.editor_mode = editorMode;

        // If there's a hidden input for template_data, sync there
        // Otherwise the existing save_template.php already handles JSON
    }

    // ── Event System ──
    function on(event, callback) {
        if (_callbacks[event]) _callbacks[event].push(callback);
    }

    function off(event, callback) {
        if (_callbacks[event]) {
            _callbacks[event] = _callbacks[event].filter(cb => cb !== callback);
        }
    }

    function trigger(event, data) {
        if (_callbacks[event]) _callbacks[event].forEach(cb => cb(data));
    }

    // ── Utilities ──
    function escapeHtml(str) {
        if (typeof str !== 'string') return str || '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function getTemplateData() { return templateData; }
    function getSelected() { return selectedElement; }
    function getMode() { return editorMode; }
    function isDirtyFlag() { return isDirty; }
    function markClean() { isDirty = false; }
    function markDirty() { isDirty = true; trigger('onChange'); }

    // ── Public API ──
    return {
        init,
        setMode, getMode,
        renderCanvasVisual,
        selectElement, deselectElement, getSelected,
        getComponent, updateComponent, addComponent, removeComponent,
        getTemplateData,
        syncDataToForm,
        isDirtyFlag, markClean, markDirty,
        on, off,
        escapeHtml,
        getSection
    };
})();
