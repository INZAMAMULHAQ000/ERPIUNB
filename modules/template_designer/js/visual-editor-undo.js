/**
 * Visual Editor — Undo/Redo
 * Snapshot-based history stack
 */
VisualEditor.undo = (() => {
    'use strict';

    const MAX_HISTORY = 50;
    let undoStack = [];
    let redoStack = [];
    let isRestoring = false;

    function init() {
        // Auto-snapshot on changes (debounced)
        VisualEditor.on('onChange', () => {
            if (!isRestoring) pushSnapshot();
        });
    }

    function pushSnapshot() {
        const data = VisualEditor.getTemplateData();
        if (!data) return;
        undoStack.push(JSON.stringify(data));
        if (undoStack.length > MAX_HISTORY) undoStack.shift();
        redoStack = []; // Clear redo on new action
    }

    function undo() {
        if (undoStack.length < 2) return; // Need at least 2 (current + previous)
        // Current state goes to redo
        const current = JSON.stringify(VisualEditor.getTemplateData());
        redoStack.push(current);

        // Pop the previous state
        undoStack.pop(); // Remove current
        const prev = undoStack[undoStack.length - 1];
        if (!prev) return;

        isRestoring = true;
        restoreState(JSON.parse(prev));
        isRestoring = false;
    }

    function redo() {
        if (!redoStack.length) return;

        const current = JSON.stringify(VisualEditor.getTemplateData());
        undoStack.push(current);

        const next = redoStack.pop();
        isRestoring = true;
        restoreState(JSON.parse(next));
        isRestoring = false;
    }

    function restoreState(data) {
        const ve = VisualEditor;
        const td = ve.getTemplateData();

        // Deep merge: replace sections, header, footer, body, styles
        if (data.page) td.page = data.page;
        if (data.header) td.header = data.header;
        if (data.footer) td.footer = data.footer;
        if (data.body) td.body = data.body;
        if (data.styles) td.styles = data.styles;

        ve.markDirty();
        ve.renderCanvasVisual();
        ve.deselectElement();
    }

    function canUndo() { return undoStack.length > 1; }
    function canRedo() { return redoStack.length > 0; }

    // Keyboard shortcuts
    document.addEventListener('keydown', e => {
        if (!VisualEditor.getMode || VisualEditor.getMode() !== 'visual') return;

        if ((e.ctrlKey || e.metaKey) && e.key === 'z') {
            e.preventDefault();
            if (e.shiftKey) { redo(); }
            else { undo(); }
        }
        if ((e.ctrlKey || e.metaKey) && e.key === 'y') {
            e.preventDefault();
            redo();
        }
        // Delete key
        if (e.key === 'Delete' || e.key === 'Backspace') {
            // Don't trigger if typing in an input
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable) return;
            const sel = VisualEditor.getSelected();
            if (sel) {
                e.preventDefault();
                VisualEditor._deleteSelectedElement();
            }
        }
        // Arrow nudge
        if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(e.key) && !e.ctrlKey && !e.metaKey) {
            const sel = VisualEditor.getSelected();
            if (!sel || e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            e.preventDefault();
            const comp = VisualEditor.getComponent(sel.sectionIdx, sel.compIdx);
            if (!comp || !comp.position) return;
            const snap = e.shiftKey ? 1 : 5;
            if (e.key === 'ArrowUp') comp.position.y = Math.max(0, comp.position.y - snap);
            if (e.key === 'ArrowDown') comp.position.y += snap;
            if (e.key === 'ArrowLeft') comp.position.x = Math.max(0, comp.position.x - snap);
            if (e.key === 'ArrowRight') comp.position.x += snap;
            VisualEditor.markDirty();
            VisualEditor.renderCanvasVisual();
            VisualEditor.selectElement(sel.sectionIdx, sel.compIdx); // Re-select to refresh properties
        }
    });

    return { init, undo, redo, pushSnapshot, canUndo, canRedo };
})();
