/**
 * Visual Editor — Resize Handles
 * 8-direction resize for selected canvas elements
 */
VisualEditor.resize = (() => {
    'use strict';

    let isResizing = false;
    let resizeData = null;

    function init() {
        document.addEventListener('mousedown', e => {
            const handle = e.target.closest('.ve-resize-handle');
            if (!handle) return;

            const el = handle.closest('.ve-element');
            if (!el) return;

            e.preventDefault();
            e.stopPropagation();

            const rect = el.getBoundingClientRect();
            const dir = handle.className.split(' ').find(c => ['nw','n','ne','w','e','sw','s','se'].includes(c));

            isResizing = true;
            resizeData = {
                el, dir,
                sectionIdx: el.dataset.section,
                compIdx: parseInt(el.dataset.comp),
                startX: e.clientX,
                startY: e.clientY,
                startW: rect.width,
                startH: rect.height,
                startLeft: el.offsetLeft,
                startTop: el.offsetTop
            };

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        });
    }

    function onMouseMove(e) {
        if (!isResizing || !resizeData) return;

        const snap = 5;
        const dx = e.clientX - resizeData.startX;
        const dy = e.clientY - resizeData.startY;
        const d = resizeData.dir;
        let newW = resizeData.startW;
        let newH = resizeData.startH;
        let newX = resizeData.startLeft;
        let newY = resizeData.startTop;

        // Width changes
        if (d.includes('e')) {
            newW = Math.max(30, resizeData.startW + dx);
        }
        if (d.includes('w')) {
            const delta = resizeData.startW - dx;
            newW = Math.max(30, delta);
            newX = resizeData.startLeft + (resizeData.startW - newW);
        }

        // Height changes
        if (d.includes('s')) {
            newH = Math.max(20, resizeData.startH + dy);
        }
        if (d.includes('n')) {
            const delta = resizeData.startH - dy;
            newH = Math.max(20, delta);
            newY = resizeData.startTop + (resizeData.startH - newH);
        }

        // Snap
        newW = Math.round(newW / snap) * snap;
        newH = Math.round(newH / snap) * snap;
        newX = Math.round(newX / snap) * snap;
        newY = Math.max(0, Math.round(newY / snap) * snap);

        // Apply to DOM
        resizeData.el.style.width = newW + 'px';
        resizeData.el.style.height = newH + 'px';
        resizeData.el.style.left = newX + 'px';
        resizeData.el.style.top = newY + 'px';
    }

    function onMouseUp() {
        if (!isResizing || !resizeData) return;

        document.removeEventListener('mousemove', onMouseMove);
        document.removeEventListener('mouseup', onMouseUp);

        // Persist position and size to templateData
        const w = parseInt(resizeData.el.style.width) || resizeData.startW;
        const h = parseInt(resizeData.el.style.height) || resizeData.startH;
        const x = parseInt(resizeData.el.style.left) || resizeData.startLeft;
        const y = parseInt(resizeData.el.style.top) || resizeData.startTop;

        const comp = VisualEditor.getComponent(resizeData.sectionIdx, resizeData.compIdx);
        if (comp) {
            comp.position = { x, y, w, h };
            VisualEditor.markDirty();
        }

        isResizing = false;
        resizeData = null;
    }

    return { init };
})();
