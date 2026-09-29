/* =============================================================
   ERP THEME CONTROLLER — IBM Light / IBM Dark
   -------------------------------------------------------------
   One place owns theming now. Loaded SYNCHRONOUSLY from <head>
   (before first paint) by includes/header.php and modules/auth/login.php.

   What it guarantees:
     1. Clicking the navbar icon restyles EVERY element instantly —
        no reload, no relogin (attribute flips on <html> AND <body>).
     2. The choice survives navigation, logout/login and browser
        restarts: written to localStorage immediately and synced to
        the PHP session + users.theme column in the background.
     3. On every page load the stored choice is applied BEFORE the
        first paint, so pages never flash the wrong theme.
     4. Live charts follow: Carbon charts via renderCarbonChart.reTheme(),
        Chart.js canvases via a direct option re-tint.
     5. Smooth ~180ms color morph during the switch (skipped when the
        OS asks for reduced motion).

   Public API:  ERPTheme.get() / .apply(t) / .toggle()
   Legacy hook: window.toggleIBMTheme() kept alive for the navbar onclick.
   ============================================================= */
(function () {
    'use strict';

    var STORE_KEY = 'erp_theme';
    var VALID = ['ibm-light', 'ibm-dark'];
    var current = 'ibm-light';

    /* ---------- storage helpers (private-mode safe) ---------- */
    function readStore() {
        try { return localStorage.getItem(STORE_KEY); } catch (e) { return null; }
    }
    function writeStore(v) {
        try { localStorage.setItem(STORE_KEY, v); } catch (e) { /* ignore */ }
    }

    /* ---------- attribute application ---------- */
    function paintAttrs(theme) {
        // <html> carries it for pre-paint rules; <body> mirrors because
        // several legacy rules key off body[data-theme="…"] directly.
        document.documentElement.setAttribute('data-theme', theme);
        if (document.body) document.body.setAttribute('data-theme', theme);
    }

    function syncIcon(theme) {
        var icon = document.getElementById('themeIcon');
        var btn = document.getElementById('themeToggle');
        if (icon) icon.className = theme === 'ibm-light' ? 'fas fa-moon' : 'fas fa-sun';
        if (btn) btn.title = theme === 'ibm-light' ? 'Switch to IBM Dark' : 'Switch to IBM Light';
    }

    /* ---------- live chart re-theming ---------- */
    function retintCharts(theme) {
        // 1) Carbon Charts (@carbon/charts UMD) — full re-render registry.
        try {
            if (window.renderCarbonChart && typeof window.renderCarbonChart.reTheme === 'function') {
                window.renderCarbonChart.reTheme();
            }
        } catch (e) { /* charts are cosmetic — never block the toggle */ }

        // 2) Chart.js instances (dashboard widgets) — retint options in place,
        //    using the SAME tokens dashboard-core.js picks at render time.
        try {
            if (typeof window.Chart !== 'undefined' && typeof window.Chart.getChart === 'function') {
                var dark = theme === 'ibm-dark';
                var txt = dark ? '#94a3b8' : '#64748b';
                var grid = dark ? '#1e293b' : '#f1f5f9';
                var canvases = document.querySelectorAll('canvas');
                for (var i = 0; i < canvases.length; i++) {
                    var ch = window.Chart.getChart(canvases[i]);
                    if (!ch || !ch.options) continue;
                    var o = ch.options;
                    if (!o.plugins) o.plugins = {};
                    if (!o.plugins.legend) o.plugins.legend = {};
                    if (o.plugins.legend.labels) o.plugins.legend.labels.color = txt;
                    if (o.scales) {
                        Object.keys(o.scales).forEach(function (k) {
                            var s = o.scales[k];
                            if (s && s.ticks) s.ticks.color = txt;
                            if (s && s.grid && s.grid.color) s.grid.color = grid;
                        });
                    }
                    ch.update('none'); // 'none' = no animation snap
                }
            }
        } catch (e) { /* ignore */ }
    }

    /* ---------- background persistence to PHP session (+ users.theme) ---------- */
    function serverSync(theme) {
        try {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', '?set_theme=' + encodeURIComponent(theme), true);
            // Header matters: without it header.php answers with a redirect and
            // the browser silently re-executes the ENTIRE page twice per click.
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.send();
        } catch (e) { /* ignore */ }
    }

    /* ---------- smoothness ---------- */
    function animateSwitch(applyFn) {
        var reduce = false;
        try { reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches; } catch (e) {}
        if (reduce) { applyFn(); return; }
        var root = document.documentElement;
        root.classList.add('theme-switching');
        applyFn();
        setTimeout(function () { root.classList.remove('theme-switching'); }, 220);
    }

    /* ---------- public API ---------- */
    function get() { return current; }

    function apply(theme, opts) {
        if (VALID.indexOf(theme) === -1) return current;
        opts = opts || {};
        current = theme;
        writeStore(theme);
        animateSwitch(function () {
            paintAttrs(theme);
            syncIcon(theme);
        });
        // Charts + event after the class flip so they sample final colors.
        requestAnimationFrame(function () {
            retintCharts(theme);
            try {
                window.dispatchEvent(new CustomEvent('erp:themechange', { detail: { theme: theme } }));
            } catch (e) {}
        });
        if (opts.persist !== false) serverSync(theme);
        return theme;
    }

    function toggle() {
        return apply(current === 'ibm-dark' ? 'ibm-light' : 'ibm-dark');
    }

    /* ---------- boot ----------
       This script runs synchronously in <head>: document.body does NOT exist
       yet, so anything needed pre-paint targets <html> only. */
    current = document.documentElement.getAttribute('data-theme') || 'ibm-light';
    var stored = readStore();

    if (VALID.indexOf(stored) !== -1 && stored !== current) {
        // Session lost its theme (logout wipes $_SESSION, fresh device, …)
        // but THIS browser remembers — restore it before first paint and
        // push it back to the server session/user row.
        current = stored;
        document.documentElement.setAttribute('data-theme', stored);
        writeStore(stored);
        setTimeout(function () { serverSync(stored); }, 400); // let the page settle first
    }

    // Mirror onto <body> the moment it exists + fix the navbar icon.
    function onReady() {
        if (document.body && document.body.getAttribute('data-theme') !== current) {
            document.body.setAttribute('data-theme', current);
        }
        syncIcon(current);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', onReady);
    } else {
        onReady();
    }

    // Cross-tab consistency: another tab toggled → follow suit (no reload).
    try {
        window.addEventListener('storage', function (e) {
            if (e.key === STORE_KEY && VALID.indexOf(e.newValue) !== -1 && e.newValue !== current) {
                current = e.newValue;
                paintAttrs(current);
                syncIcon(current);
                retintCharts(current);
            }
        });
    } catch (e) {}

    window.ERPTheme = { get: get, apply: apply, toggle: toggle };
    // Navbar button calls toggleIBMTheme() — keep that name working forever.
    window.toggleIBMTheme = toggle;
})();
