<?php
/**
 * CLIENT PREVIEW MOCK — new navbar + sidebar look (cover only).
 *
 * Purely visual. Only the top navbar and left sidebar areas get covered by the
 * mock panels; the real body content stays visible and fully interactive.
 * All existing workflows are unchanged. Click the glowing icon in the top bar
 * to toggle, or press ESC / "Back to App" to exit.
 *
 * LINKED CARDS (navigate to real destinations):
 *   CRM / HR / Finance -> existing ERP modules (safe to change below)
 *   CAFM               -> external site (opens in a new tab)
 *   SCM / MIS-BI / GPT -> not built yet -> remain mock-only (no link)
 *
 * REMOVAL (smooth peel-off, nothing else in the app is touched):
 *   1. templates/navbar.php   -> delete the block between the
 *        "CLIENT PREVIEW MOCK — START / END" comments.
 *   2. includes/footer.php    -> delete the require_once block between the
 *        "CLIENT PREVIEW MOCK — START / END" comments.
 *   3. Delete this file: templates/mock_preview.php
 * No other file in the project is modified by this preview.
 */

$mpBase = rtrim(BASE_URL, '/');
$mpLinks = [
    'crm'     => ['url' => $mpBase . '/modules/leads/index.php',                'blank' => false],
    'hr'      => ['url' => $mpBase . '/modules/people_management/index.php',    'blank' => false],
    'finance' => ['url' => $mpBase . '/modules/finance/index.php',              'blank' => false],
    'cafm'    => ['url' => 'https://cafm.genfuratechnologies.co.in/dashboard/overview', 'blank' => true],
];
?>
<style>
/* ============================================================
   CLIENT PREVIEW MOCK — glowing trigger + navbar/sidebar cover
   ============================================================ */

/* --- Glowing trigger icon in the real navbar --- */
.mock-preview-toggle {
    position: relative;
    animation: mpButtonGlow 2.4s ease-in-out infinite;
}
.mock-preview-toggle i {
    background: linear-gradient(135deg, #a78bfa, #6366f1, #22d3ee);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}
.mock-preview-toggle:hover { animation-play-state: paused; }
@keyframes mpButtonGlow {
    0%, 100% { box-shadow: 0 0 0 0 rgba(139, 92, 246, 0); }
    50%      { box-shadow: 0 0 14px 3px rgba(139, 92, 246, 0.45); }
}

/* ============================================================
   MOCK NAVBAR — covers the real top bar (4rem) only
   ============================================================ */
.mp-nav {
    position: fixed;
    top: 0; left: 0; right: 0;
    height: 4rem;
    z-index: 60;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0 1.25rem;
    background: rgba(255, 255, 255, .94);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-bottom: 1px solid #e2e8f0;
    box-shadow: 0 6px 24px rgba(15, 23, 42, .06);
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-110%);
    transition: transform .32s cubic-bezier(.34, 1.3, .64, 1), opacity .25s ease;
}
body.mock-preview-on .mp-nav {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.mp-brand { display: flex; align-items: center; gap: .7rem; min-width: 0; }
.mp-hamburger {
    display: none;
    width: 36px; height: 36px; border-radius: 10px;
    align-items: center; justify-content: center;
    border: 1px solid #e2e8f0; background: #fff; color: #475569; cursor: pointer;
}
.mp-logo {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 1rem;
    background: linear-gradient(135deg, #6366f1, #a855f7);
    box-shadow: 0 6px 16px rgba(99, 102, 241, .35);
}
.mp-wordmark { font-size: 1.05rem; font-weight: 800; letter-spacing: -.02em; color: #0f172a; }
.mp-badge {
    font-size: .58rem; font-weight: 800; letter-spacing: .12em;
    padding: 3px 8px; border-radius: 999px;
    color: #92400e; background: #fef3c7; border: 1px solid #fcd34d;
    white-space: nowrap;
}
.mp-search {
    flex: 1; max-width: 460px; position: relative; margin: 0 1rem;
}
.mp-search input {
    width: 100%; padding: .55rem 2.6rem .55rem 2.4rem;
    border-radius: 10px; border: 1px solid #e2e8f0;
    background: #f1f5f9; font-size: .85rem; outline: none;
    transition: all .15s;
}
.mp-search input:focus {
    background: #fff; border-color: #a5b4fc;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, .12);
}
.mp-search > i { position: absolute; left: .9rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: .8rem; }
.mp-search kbd {
    position: absolute; right: .55rem; top: 50%; transform: translateY(-50%);
    font-size: .62rem; font-weight: 700; color: #64748b;
    background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 2px 6px;
}
.mp-actions { display: flex; align-items: center; gap: .5rem; }
.mp-icon-btn {
    position: relative;
    width: 38px; height: 38px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #475569; background: #fff; border: 1px solid #e2e8f0;
    cursor: pointer; transition: all .15s; font-size: .95rem;
}
.mp-icon-btn:hover { background: #eef2ff; color: #4f46e5; }
.mp-icon-btn .dot { position: absolute; top: 8px; right: 9px; width: 7px; height: 7px; border-radius: 50%; background: #ef4444; }
.mp-avatar {
    width: 36px; height: 36px; border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #a855f7); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: .78rem; font-weight: 700;
}
.mp-close {
    display: flex; align-items: center; gap: .45rem;
    padding: .55rem .9rem; border-radius: 10px;
    border: none; cursor: pointer; font-size: .82rem; font-weight: 700; color: #fff;
    background: #0f172a; transition: all .15s;
}
.mp-close:hover { background: #1e293b; transform: translateY(-1px); }
.mp-x {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    border: none; cursor: pointer; font-size: .95rem;
    color: #94a3b8; background: transparent; transition: all .15s;
}
.mp-x:hover { color: #ef4444; background: #fef2f2; }

/* ============================================================
   MOCK SIDEBAR — covers the real left sidebar (16rem) only
   ============================================================ */
.mp-sidebar {
    position: fixed;
    top: 4rem; left: 0; bottom: 0;
    width: 16rem;
    z-index: 55;
    display: flex;
    flex-direction: column;
    background: #ffffff;
    border-right: 1px solid #e2e8f0;
    overflow-y: auto;
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    opacity: 0;
    visibility: hidden;
    transform: translateX(-110%);
    transition: transform .32s cubic-bezier(.34, 1.3, .64, 1), opacity .25s ease;
}
body.mock-preview-on .mp-sidebar {
    opacity: 1;
    visibility: visible;
    transform: translateX(0);
}

.mp-sidebar-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 1.1rem 1.1rem .7rem;
}
.mp-sidebar-title {
    font-size: .62rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: .14em; color: #94a3b8;
}
.mp-sidebar-tag {
    font-size: .56rem; font-weight: 800; letter-spacing: .1em;
    padding: 2px 8px; border-radius: 999px;
    color: #6366f1; background: #eef2ff; border: 1px solid #c7d2fe;
}
.mp-cards { display: flex; flex-direction: column; gap: .55rem; padding: .2rem .85rem .6rem; flex: 1; }
.mp-card {
    display: flex; align-items: center; gap: .8rem; width: 100%; text-align: left;
    padding: .72rem .8rem; border-radius: 13px;
    border: 1px solid #eef2f7; background: #fbfcfe; cursor: pointer;
    transition: all .18s; position: relative;
    flex: 1;
    text-decoration: none;
    box-sizing: border-box;
}
.mp-card:hover {
    transform: translateY(-1px);
    border-color: #c7d2fe;
    background: #fff;
    box-shadow: 0 8px 20px rgba(99, 102, 241, .08);
}
.mp-card.active {
    background: #fff;
    border-color: var(--mp-color, #6366f1);
    box-shadow: 0 0 0 3px var(--mp-ring, rgba(99, 102, 241, .15)), 0 8px 20px rgba(99, 102, 241, .10);
}
.mp-card-icon {
    width: 38px; height: 38px; min-width: 38px; border-radius: 11px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: .98rem;
    box-shadow: 0 4px 10px rgba(0, 0, 0, .12);
}
.mp-card-text { display: flex; flex-direction: column; gap: 2px; min-width: 0; flex: 1; }
.mp-card-name { font-size: .84rem; font-weight: 700; color: #0f172a; }
.mp-card-sub {
    font-size: .66rem; color: #64748b;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.mp-goto { margin-left: auto; font-size: .65rem; color: #c7d2fe; flex-shrink: 0; }
.mp-card.active .mp-goto { color: var(--mp-color, #6366f1); }
.mp-card:hover .mp-goto { color: var(--mp-color, #6366f1); }
.mp-sidebar-foot {
    margin-top: auto;
    padding: .8rem 1.1rem 1.1rem;
    font-size: .66rem; color: #94a3b8; text-align: center;
}

/* --- Floating "preview mode" pill (bottom-right, dismissible) --- */
.mp-floating {
    position: fixed;
    right: 1rem; bottom: 1rem;
    z-index: 65;
    display: flex; align-items: center; gap: .5rem;
    padding: .55rem .8rem;
    border-radius: 999px;
    background: #0f172a; color: #fff; font-size: .75rem; font-weight: 700;
    box-shadow: 0 10px 30px rgba(15, 23, 42, .25);
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    opacity: 0; visibility: hidden; transform: translateY(12px);
    transition: opacity .25s ease, transform .25s ease;
}
body.mock-preview-on .mp-floating {
    opacity: 1; visibility: visible; transform: translateY(0);
}
.mp-floating i { color: #a78bfa; }
.mp-floating button {
    width: 22px; height: 22px; border-radius: 50%; border: none; cursor: pointer;
    background: rgba(255, 255, 255, .14); color: #fff; font-size: .6rem;
    display: flex; align-items: center; justify-content: center;
}
.mp-floating button:hover { background: rgba(255, 255, 255, .28); }

/* --- Responsive: mock sidebar becomes a drawer on mobile --- */
@media (max-width: 767px) {
    .mp-hamburger { display: flex; }
    .mp-sidebar { width: min(16rem, 85vw); box-shadow: 4px 0 20px rgba(0, 0, 0, .3); }
    .mp-sidebar.mp-sidebar-closed {
        opacity: 0; visibility: hidden; transform: translateX(-110%);
    }
    .mp-search { display: none; }
    .mp-wordmark { display: none; }
    .mp-avatar, .mp-icon-btn { display: none; }
}
</style>

<!-- ===== Mock Navbar (covers the real top bar only) ===== -->
<nav class="mp-nav" id="mockPreviewNav" aria-hidden="true" aria-label="New Navbar Preview">
    <div class="mp-brand">
        <button class="mp-hamburger" onclick="toggleMockSidebar()" title="Toggle preview sidebar"><i class="fas fa-bars"></i></button>
        <span class="mp-logo"><i class="fas fa-bolt"></i></span>
        <span class="mp-wordmark">Qaswa</span>
        <span class="mp-badge">CLIENT PREVIEW</span>
    </div>
    <div class="mp-search">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Search anything…" disabled>
        <kbd>Ctrl K</kbd>
    </div>
    <div class="mp-actions">
        <button class="mp-icon-btn" title="Notifications"><i class="fas fa-bell"></i><span class="dot"></span></button>
        <button class="mp-icon-btn" title="Help"><i class="fas fa-circle-question"></i></button>
        <span class="mp-avatar">AD</span>
        <button class="mp-close" onclick="closeMockPreview()"><i class="fas fa-arrow-left"></i>Back to App</button>
        <button class="mp-x" onclick="closeMockPreview()" title="Close preview (ESC)"><i class="fas fa-times"></i></button>
    </div>
</nav>

<!-- ===== Mock Sidebar (covers the real left sidebar only) ===== -->
<aside class="mp-sidebar" id="mockPreviewSidebar" aria-hidden="true" aria-label="New Sidebar Preview">
    <div class="mp-sidebar-head">
        <span class="mp-sidebar-title">Modules</span>
        <span class="mp-sidebar-tag">Preview</span>
    </div>
    <div class="mp-cards" id="mockPreviewCards"></div>
    <div class="mp-sidebar-foot">Design mock — the real app is unchanged</div>
</aside>

<!-- ===== Floating preview pill ===== -->
<div class="mp-floating" id="mockPreviewFloating">
    <i class="fas fa-eye"></i> Preview mode — new navbar
    <button onclick="closeMockPreview()" title="Exit preview"><i class="fas fa-times"></i></button>
</div>

<script>
/* ============================================================
   CLIENT PREVIEW MOCK — data & behaviour (global, no side effects)
   ============================================================ */
var MOCK_MODULES = [
    { key: 'crm',     name: 'CRM Modules',      icon: 'fa-handshake',      color: '#6366f1', grad: 'linear-gradient(135deg, #6366f1, #8b5cf6)',    sub: 'Leads · Clients · Deals' },
    { key: 'hr',      name: 'HR Modules',       icon: 'fa-user-tie',       color: '#10b981', grad: 'linear-gradient(135deg, #10b981, #0ea5e9)',    sub: 'Employees · Attendance · Leave' },
    { key: 'finance', name: 'Finance Modules',  icon: 'fa-coins',          color: '#2563eb', grad: 'linear-gradient(135deg, #2563eb, #06b6d4)',    sub: 'Accounting · Banking · AR / AP' },
    { key: 'scm',     name: 'SCM Modules',      icon: 'fa-truck',          color: '#f59e0b', grad: 'linear-gradient(135deg, #f59e0b, #f97316)',    sub: 'Procurement · Inventory' },
    { key: 'cafm',    name: 'CAFM Modules',     icon: 'fa-building',       color: '#ef4444', grad: 'linear-gradient(135deg, #ef4444, #ec4899)',    sub: 'Assets · Maintenance · Facilities' },
    { key: 'mis',     name: 'MIS-BI Reports',   icon: 'fa-chart-line',     color: '#8b5cf6', grad: 'linear-gradient(135deg, #8b5cf6, #d946ef)',    sub: 'Analytics · Dashboards · Reports' },
    { key: 'gpts',    name: 'Smart-BiZ GPTs',   icon: 'fa-robot',          color: '#0ea5e9', grad: 'linear-gradient(135deg, #0ea5e9, #22d3ee)',    sub: 'AI Assistants · Copilots' }
];

/* Links injected by PHP (BASE_URL-based so they work from any page depth) */
var MOCK_MODULE_LINKS = <?= json_encode($mpLinks) ?>;

function buildMockCards() {
    var wrap = document.getElementById('mockPreviewCards');
    if (!wrap) return;
    wrap.innerHTML = '';
    MOCK_MODULES.forEach(function (m) {
        var linkInfo = MOCK_MODULE_LINKS[m.key] || null;
        var el;
        if (linkInfo) {
            el = document.createElement('a');
            el.href = linkInfo.url;
            if (linkInfo.blank) { el.target = '_blank'; el.rel = 'noopener'; }
        } else {
            el = document.createElement('button');
            el.type = 'button';
        }
        el.className = 'mp-card';
        el.dataset.key = m.key;
        el.style.setProperty('--mp-color', m.color);
        el.style.setProperty('--mp-ring', m.color + '2e');
        el.innerHTML =
            '<span class="mp-card-icon" style="background:' + m.grad + '"><i class="fas ' + m.icon + '"></i></span>' +
            '<span class="mp-card-text"><span class="mp-card-name">' + m.name + '</span>' +
            '<span class="mp-card-sub">' + m.sub + '</span></span>';
        if (linkInfo) {
            var goto = document.createElement('span');
            goto.className = 'mp-goto';
            goto.innerHTML = '<i class="fas ' + (linkInfo.blank ? 'fa-arrow-up-right-from-square' : 'fa-arrow-right') + '"></i>';
            el.appendChild(goto);
        }
        el.addEventListener('click', function () { selectMockCard(m.key); });
        wrap.appendChild(el);
    });
    var first = wrap.querySelector('.mp-card');
    if (first) first.classList.add('active');
}

function selectMockCard(key) {
    var cards = document.querySelectorAll('#mockPreviewCards .mp-card');
    cards.forEach(function (c) { c.classList.toggle('active', c.dataset.key === key); });
}

function toggleMockPreview() {
    if (document.body.classList.contains('mock-preview-on')) closeMockPreview();
    else openMockPreview();
}

function openMockPreview() {
    document.body.classList.add('mock-preview-on');
    ['mockPreviewNav', 'mockPreviewSidebar'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.setAttribute('aria-hidden', 'false');
    });
}

function closeMockPreview() {
    document.body.classList.remove('mock-preview-on');
    ['mockPreviewNav', 'mockPreviewSidebar'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.setAttribute('aria-hidden', 'true');
    });
}

function toggleMockSidebar() {
    var s = document.getElementById('mockPreviewSidebar');
    if (s) s.classList.toggle('mp-sidebar-closed');
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeMockPreview();
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', buildMockCards);
} else {
    buildMockCards();
}
</script>
