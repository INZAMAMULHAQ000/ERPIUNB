/* =============================================================
   Fluent Dropdown — upgrades native <select> elements inside
   .erp-main into Microsoft 365-style dropdowns.

   How it works:
   - The native <select> is kept in the DOM (opacity 0, absolute,
     pointer-events none) so form submission, required validation
     and any JS "change" listeners still behave exactly as before.
   - A styled trigger (white, 1px stroke, 4px corners, chevron)
     is rendered in its place, opening a callout listbox with a
     blue checkmark on the selected option.
   - The list re-syncs when options change (cascading selects) and
     on form.reset().
   - Keyboard: Enter/Space opens, ArrowUp/Down navigates, Enter
     selects, Escape closes. Click outside closes.
   ============================================================= */
(function () {
    'use strict';

    function build(select) {
        if (!select || select.dataset.fluentDD === '1') return;
        if (select.multiple || select.closest('.fluent-dd')) return;

        select.dataset.fluentDD = '1';

        var wrap = document.createElement('div');
        wrap.className = 'fluent-dd' + (select.classList.contains('w-full') ? ' fluent-dd-block' : '');

        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'fluent-dd-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');

        var value = document.createElement('span');
        value.className = 'fluent-dd-value';
        var caret = document.createElement('i');
        caret.className = 'fluent-dd-caret fas fa-chevron-down';
        caret.setAttribute('aria-hidden', 'true');

        trigger.appendChild(value);
        trigger.appendChild(caret);

        var menu = document.createElement('div');
        menu.className = 'fluent-dd-menu';
        menu.setAttribute('role', 'listbox');

        // Move the native select inside the new wrapper
        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(select);
        wrap.appendChild(trigger);
        wrap.appendChild(menu);

        select.classList.add('fluent-dd-native');
        select.setAttribute('tabindex', '-1');
        select.setAttribute('aria-hidden', 'true');

        // Preserve width of non-full-width selects (fallback if measured in a hidden modal)
        if (!select.classList.contains('w-full')) {
            var w = select.offsetWidth;
            if (w > 0) wrap.style.width = w + 'px';
            else wrap.classList.add('fluent-dd-auto');
        }

        var activeIndex = -1;

        function optionList() { return menu.querySelectorAll('.fluent-dd-option'); }

        function buildOptions() {
            menu.innerHTML = '';
            activeIndex = -1;
            Array.prototype.forEach.call(select.options, function (opt, idx) {
                if (opt.hidden) return;
                var item = document.createElement('button');
                item.type = 'button';
                item.className = 'fluent-dd-option' + (opt.selected ? ' is-selected' : '') + (opt.disabled ? ' is-disabled' : '');
                item.setAttribute('role', 'option');
                item.setAttribute('data-index', String(idx));
                if (opt.selected) item.setAttribute('aria-selected', 'true');
                item.textContent = opt.textContent;

                // Prevent the option click from stealing focus from the trigger
                item.addEventListener('mousedown', function (e) { e.preventDefault(); });
                item.addEventListener('click', function () {
                    if (opt.disabled) return;
                    if (select.value !== opt.value) {
                        select.value = opt.value;
                        select.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    closeMenu();
                    syncTrigger();
                });
                menu.appendChild(item);
            });
        }

        function syncTrigger() {
            var idx = select.selectedIndex;
            var opt = select.options[idx];
            value.textContent = opt ? opt.textContent : '';
            value.classList.toggle('is-placeholder', !select.value);
            Array.prototype.forEach.call(optionList(), function (it) {
                var o = select.options[Number(it.getAttribute('data-index'))];
                var isSel = !!(o && o.selected);
                it.classList.toggle('is-selected', isSel);
                if (isSel) it.setAttribute('aria-selected', 'true');
                else it.removeAttribute('aria-selected');
            });
        }

        function syncDisabled() {
            var disabled = select.disabled;
            wrap.classList.toggle('is-disabled', disabled);
            trigger.disabled = disabled;
        }

        function closeMenu() {
            menu.classList.remove('is-open');
            wrap.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
        }

        function positionMenu() {
            menu.style.top = 'auto';
            menu.style.bottom = 'auto';
            var rect = wrap.getBoundingClientRect();
            var below = window.innerHeight - rect.bottom - 8;
            if (below < 240 && rect.top > below) {
                menu.style.bottom = 'calc(100% + 4px)';
            } else {
                menu.style.top = 'calc(100% + 4px)';
            }
        }

        function openMenu() {
            // Close any other open dropdown
            var open = document.querySelectorAll('.fluent-dd.is-open');
            Array.prototype.forEach.call(open, function (w) {
                if (w !== wrap) {
                    w.classList.remove('is-open');
                    var m = w.querySelector('.fluent-dd-menu');
                    if (m) m.classList.remove('is-open');
                    var t = w.querySelector('.fluent-dd-trigger');
                    if (t) t.setAttribute('aria-expanded', 'false');
                }
            });

            buildOptions();
            syncTrigger();
            positionMenu();
            menu.classList.add('is-open');
            wrap.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');

            var items = optionList();
            var selectedItem = menu.querySelector('.is-selected');
            if (selectedItem) {
                activeIndex = Array.prototype.indexOf.call(items, selectedItem);
                highlight(activeIndex);
                selectedItem.scrollIntoView({ block: 'nearest' });
            }
        }

        function highlight(i) {
            Array.prototype.forEach.call(optionList(), function (it, idx) {
                it.classList.toggle('is-active', idx === i);
            });
        }

        function move(dir) {
            var items = optionList();
            if (!items.length) return;
            do {
                activeIndex = (activeIndex + dir + items.length) % items.length;
            } while (items[activeIndex] && items[activeIndex].classList.contains('is-disabled'));
            highlight(activeIndex);
            items[activeIndex].scrollIntoView({ block: 'nearest' });
        }

        function selectActive() {
            var items = optionList();
            if (activeIndex >= 0 && items[activeIndex]) items[activeIndex].click();
        }

        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            if (select.disabled) return;
            if (menu.classList.contains('is-open')) closeMenu();
            else openMenu();
        });

        trigger.addEventListener('keydown', function (e) {
            var isOpen = menu.classList.contains('is-open');
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (!isOpen) openMenu();
                else move(e.key === 'ArrowDown' ? 1 : -1);
            } else if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                if (!isOpen) openMenu();
                else selectActive();
            } else if (e.key === 'Escape' && isOpen) {
                closeMenu();
                trigger.focus();
            }
        });

        // Re-sync when the select changes externally (cascading filters etc.)
        select.addEventListener('change', function () {
            buildOptions();
            syncTrigger();
        });

        // Re-sync when options are added/removed dynamically
        var obs = new MutationObserver(function () {
            buildOptions();
            syncTrigger();
        });
        obs.observe(select, { childList: true, attributes: true, subtree: true });

        // Track disabled state
        syncDisabled();
        var obsDis = new MutationObserver(syncDisabled);
        obsDis.observe(select, { attributes: true, attributeFilter: ['disabled'] });

        // Keep the trigger in sync if the form is reset
        var form = select.form;
        if (form) {
            form.addEventListener('reset', function () {
                setTimeout(function () { buildOptions(); syncTrigger(); }, 0);
            });
        }

        buildOptions();
        syncTrigger();
    }

    function enhanceAll(scope) {
        Array.prototype.forEach.call((scope || document).querySelectorAll('.erp-main select'), function (sel) {
            if (!sel.multiple) build(sel);
        });
    }

    document.addEventListener('DOMContentLoaded', function () { enhanceAll(); });

    // Auto-enhance selects added later (modals, dynamically built forms)
    var docObs = new MutationObserver(function (muts) {
        muts.forEach(function (m) {
            Array.prototype.forEach.call(m.addedNodes, function (n) {
                if (!n || n.nodeType !== 1) return;
                if (n.matches && n.matches('select') && !n.multiple) build(n);
                if (n.querySelectorAll) enhanceAll(n);
            });
        });
    });
    if (document.body) docObs.observe(document.body, { childList: true, subtree: true });
})();
