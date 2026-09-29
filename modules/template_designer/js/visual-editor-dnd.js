/**
 * Visual Editor — Drag & Drop
 * Palette-to-canvas dragging + element move on canvas
 */
VisualEditor.dnd = (() => {
    'use strict';

    let isDragging = false;
    let dragData = null;  // { type, section, compIdx, offsetX, offsetY, startX, startY }
    let ghostEl = null;
    let isMove = false;

    // ── Init: set up palette items as draggable ──
    function init() {
        document.querySelectorAll('.ve-palette-item').forEach(item => {
            item.addEventListener('dragstart', onPaletteDragStart);
            item.addEventListener('dragend', onPaletteDragEnd);
        });

        // Register drop on both classic and visual mode canvases
        document.querySelectorAll('#template-canvas, #ve-canvas').forEach(canvas => {
            canvas.addEventListener('dragover', onCanvasDragOver);
            canvas.addEventListener('dragenter', onCanvasDragEnter);
            canvas.addEventListener('dragleave', onCanvasDragLeave);
            canvas.addEventListener('drop', onCanvasDrop);
        });

        // Move on canvas via mousedown already handled in core
        // We add move logic here by hooking into element mousedown
        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
    }

    // ── Palette Drag ──
    function onPaletteDragStart(e) {
        const type = e.target.dataset.type || e.target.closest('[data-type]')?.dataset.type;
        if (!type) return;

        e.dataTransfer.setData('text/plain', type);
        e.dataTransfer.effectAllowed = 'copy';
        e.target.classList.add('dragging');

        dragData = { type, from: 'palette' };
    }

    function onPaletteDragEnd(e) {
        e.target.classList.remove('dragging');
        dragData = null;
    }

    // ── Canvas Drop Target ──
    function onCanvasDragEnter(e) {
        e.preventDefault();
        canvasEl.classList.add('drag-over');
    }

    function onCanvasDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
    }

    function onCanvasDragLeave(e) {
        canvasEl.classList.remove('drag-over');
    }

    function onCanvasDrop(e) {
        e.preventDefault();
        canvasEl.classList.remove('drag-over');

        const compType = e.dataTransfer.getData('text/plain');
        if (!compType) return;

        // Calculate drop position relative to canvas
        const canvasRect = canvasEl.getBoundingClientRect();
        const x = e.clientX - canvasRect.left + canvasEl.scrollLeft - 60; // center the element
        const y = e.clientY - canvasRect.top + canvasEl.scrollTop - 20;

        // Snap to grid
        const snap = 5;
        const sx = Math.round(x / snap) * snap;
        const sy = Math.max(0, Math.round(y / snap) * snap);

        // Create the component with default config and position
        const comp = createDefaultComponent(compType, sx, sy);
        if (!comp) return;

        // Add to body section 0 (or create if needed)
        const ve = VisualEditor;
        if (!ve.getTemplateData().body.sections.length) {
            ve.getTemplateData().body.sections.push({ type: 'section', label: 'Body', components: [] });
        }
        ve.addComponent(0, comp);

        // Select the newly added element
        const section = ve.getSection(0);
        const idx = section.components.length - 1;
        ve.selectElement(0, idx);

        showToast('Added ' + compType, 'success');
    }

    // ── Move Elements on Canvas ──
    function onElementMouseDown(e) {
        if (e.button !== 0) return;
        const el = e.currentTarget;

        // Don't start move if clicking a resize handle
        if (e.target.classList.contains('ve-resize-handle')) return;

        isMove = true;
        isDragging = true;

        const rect = el.getBoundingClientRect();
        const canvasRect = canvasEl.getBoundingClientRect();

        dragData = {
            from: 'canvas',
            el,
            sectionIdx: el.dataset.section,
            compIdx: parseInt(el.dataset.comp),
            offsetX: e.clientX - rect.left,
            offsetY: e.clientY - rect.top,
            startX: e.clientX,
            startY: e.clientY
        };

        el.classList.add('dragging');
        e.preventDefault();
    }

    function onMouseMove(e) {
        if (!isDragging || !dragData) return;

        if (dragData.from === 'canvas') {
            const canvasRect = canvasEl.getBoundingClientRect();
            const snap = 5;

            let newX = e.clientX - canvasRect.left - dragData.offsetX;
            let newY = e.clientY - canvasRect.top - dragData.offsetY;

            // Apply bounds (don't go negative)
            newX = Math.max(0, newX);
            newY = Math.max(0, newY);

            // Snap to grid
            newX = Math.round(newX / snap) * snap;
            newY = Math.round(newY / snap) * snap;

            // Update element position visually
            dragData.el.style.left = newX + 'px';
            dragData.el.style.top = newY + 'px';
        }
    }

    function onMouseUp(e) {
        if (!isDragging || !dragData) return;

        if (dragData.from === 'canvas' && dragData.el) {
            dragData.el.classList.remove('dragging');

            // Read final position from style
            const left = parseInt(dragData.el.style.left) || 0;
            const top = parseInt(dragData.el.style.top) || 0;

            // Get current position for size
            const comp = VisualEditor.getComponent(dragData.sectionIdx, dragData.compIdx);
            if (comp) {
                const pos = comp.position || { w: 200, h: 40 };
                pos.x = left;
                pos.y = top;
                comp.position = pos;
                VisualEditor.markDirty();
            }
        }

        isDragging = false;
        isMove = false;
        dragData = null;
    }

    // ── Helpers ──
    function createDefaultComponent(type, x, y) {
        const comp = { type, label: type.charAt(0).toUpperCase() + type.slice(1), position: { x, y, w: 200, h: 40 } };

        switch (type) {
            case 'text':
                comp.content = '{{report_title}}';
                comp.styles = { fontSize: 14, bold: false, align: 'left', color: '#1e293b' };
                comp.position.w = 300; comp.position.h = 36;
                break;
            case 'field':
                comp.placeholder = '{{placeholder}}';
                comp.default = '';
                comp.hide_if_empty = false;
                comp.position.w = 200; comp.position.h = 32;
                break;
            case 'table':
                comp.data_key = 'report_data';
                comp.columns = [
                    { label: 'Column 1', key: 'col1', width: 25 },
                    { label: 'Column 2', key: 'col2', width: 25 },
                    { label: 'Column 3', key: 'col3', width: 25 },
                    { label: 'Column 4', key: 'col4', width: 25 }
                ];
                comp.position.w = 700; comp.position.h = 200;
                break;
            case 'image':
                comp.src = '';
                comp.width = 150;
                comp.align = 'left';
                comp.position.w = 150; comp.position.h = 80;
                break;
            case 'divider':
                comp.style = 'solid';
                comp.color = '#e5e7eb';
                comp.position.w = 700; comp.position.h = 20;
                break;
            case 'qrcode':
                comp.data = '{{qrcode_data}}';
                comp.size = 80;
                comp.position.w = 80; comp.position.h = 80;
                break;
            case 'barcode':
                comp.data = '{{barcode_data}}';
                comp.format = 'code128';
                comp.position.w = 200; comp.position.h = 60;
                break;
            case 'signature':
                comp.label = 'Signature:';
                comp.show_label = true;
                comp.position.w = 200; comp.position.h = 60;
                break;
            case 'term':
                comp.content = 'Terms and conditions text...';
                comp.position.w = 700; comp.position.h = 80;
                break;
            case 'html':
                comp.html = '<p>Custom HTML</p>';
                comp.position.w = 400; comp.position.h = 80;
                break;
            default:
                return null;
        }
        return comp;
    }

    function showToast(msg, type) {
        let toast = document.querySelector('.ve-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 've-toast';
            document.body.appendChild(toast);
        }
        toast.textContent = msg;
        toast.className = 've-toast ' + (type || '');
        // Force reflow
        void toast.offsetWidth;
        toast.classList.add('show');
        clearTimeout(toast._timer);
        toast._timer = setTimeout(() => toast.classList.remove('show'), 2000);
    }

    // ── Canvas reference ──
    const canvasEl = document.getElementById('template-canvas');

    return { init, showToast };
})();

// Hook into core's element mousedown
// After core renders, we bind our move handler
VisualEditor.on('onRender', () => {
    document.querySelectorAll('.ve-element').forEach(el => {
        el.removeEventListener('mousedown', VisualEditor.dnd._onMouseDown);
        el.addEventListener('mousedown', VisualEditor.dnd._onMouseDown = (e) => {
            if (e.target.classList.contains('ve-resize-handle')) return;
            VisualEditor.dnd._handleMoveStart(e, el);
        });
    });
});

VisualEditor.dnd._handleMoveStart = function(e, el) {
    if (e.button !== 0) return;
    const dragData = {
        from: 'canvas',
        el,
        sectionIdx: el.dataset.section,
        compIdx: parseInt(el.dataset.comp),
        offsetX: e.clientX - el.getBoundingClientRect().left,
        offsetY: e.clientY - el.getBoundingClientRect().top
    };
    el.classList.add('dragging');

    const onMove = (ev) => {
        const canvasRect = el.parentElement.getBoundingClientRect();
        const snap = 5;
        let newX = Math.max(0, Math.round((ev.clientX - canvasRect.left - dragData.offsetX) / snap) * snap);
        let newY = Math.max(0, Math.round((ev.clientY - canvasRect.top - dragData.offsetY) / snap) * snap);
        el.style.left = newX + 'px';
        el.style.top = newY + 'px';
    };

    const onUp = () => {
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
        el.classList.remove('dragging');

        const left = parseInt(el.style.left) || 0;
        const top = parseInt(el.style.top) || 0;
        const comp = VisualEditor.getComponent(dragData.sectionIdx, dragData.compIdx);
        if (comp) {
            const pos = comp.position || { w: 200, h: 40 };
            pos.x = left;
            pos.y = top;
            comp.position = pos;
            VisualEditor.markDirty();
        }
    };

    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);
    e.preventDefault();
};
