<?php
$pageTitle = 'Appearance';
require_once '../../includes/header.php';
?>

<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Appearance</h1>
            <p class="text-gray-500 text-sm mt-1">Choose your preferred theme for the application</p>
        </div>
    </div>
</div>

<!-- Theme Selection Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-3xl">
    <!-- IBM Light Theme Card -->
    <button onclick="setActiveTheme('ibm-light')" id="themeCardIbmLight"
        class="group relative bg-white rounded-2xl shadow-sm border-2 border-slate-200 p-6 text-left transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-blue-500/30 cursor-pointer">
        <div id="ibmLightActiveBadge"
            class="absolute -top-2.5 -right-2.5 w-7 h-7 rounded-full bg-blue-600 text-white flex items-center justify-center shadow-md shadow-blue-500/30 opacity-0 scale-75 transition-all duration-200">
            <i class="fas fa-check text-[10px]"></i>
        </div>
        <div class="rounded-xl overflow-hidden mb-5 border border-slate-200">
            <div class="h-6 bg-[#161616] flex items-center px-3 gap-2 border-b border-[#393939]">
                <div class="w-3 h-3 rounded bg-[#0f62fe]"></div>
                <div class="w-16 h-1.5 rounded-full bg-[#525252]"></div>
                <div class="ml-auto flex gap-1">
                    <div class="w-1.5 h-1.5 rounded-full bg-[#6f6f6f]"></div>
                    <div class="w-1.5 h-1.5 rounded-full bg-[#6f6f6f]"></div>
                </div>
            </div>
            <div class="p-4 bg-[#f4f4f4]">
                <div class="flex gap-3">
                    <div class="w-1/4 h-24 rounded-lg bg-[#ffffff] shadow-sm border border-[#e0e0e0] p-2">
                        <div class="w-3/4 h-2 rounded bg-[#0f62fe] mb-2"></div>
                        <div class="w-full h-1.5 rounded bg-[#e0e0e0] mb-1.5"></div>
                        <div class="w-2/3 h-1.5 rounded bg-[#e0e0e0]"></div>
                    </div>
                    <div class="w-1/4 h-24 rounded-lg bg-[#ffffff] shadow-sm border border-[#e0e0e0] p-2">
                        <div class="w-3/4 h-2 rounded bg-[#24a148] mb-2"></div>
                        <div class="w-full h-1.5 rounded bg-[#e0e0e0] mb-1.5"></div>
                        <div class="w-2/3 h-1.5 rounded bg-[#e0e0e0]"></div>
                    </div>
                    <div class="w-1/4 h-24 rounded-lg bg-[#ffffff] shadow-sm border border-[#e0e0e0] p-2">
                        <div class="w-3/4 h-2 rounded bg-[#da1e28] mb-2"></div>
                        <div class="w-full h-1.5 rounded bg-[#e0e0e0] mb-1.5"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
                <i class="fas fa-sun text-blue-600 text-lg"></i>
            </div>
            <div>
                <h3 class="font-semibold text-slate-800">IBM Light</h3>
                <p class="text-xs text-slate-400 mt-0.5">Carbon gray-10, IBM Plex, flat blue</p>
            </div>
        </div>
    </button>

    <!-- IBM Dark Theme Card -->
    <button onclick="setActiveTheme('ibm-dark')" id="themeCardIbmDark"
        class="group relative bg-white rounded-2xl shadow-sm border-2 border-slate-200 p-6 text-left transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-blue-500/30 cursor-pointer">
        <div id="ibmDarkActiveBadge"
            class="absolute -top-2.5 -right-2.5 w-7 h-7 rounded-full bg-blue-600 text-white flex items-center justify-center shadow-md shadow-blue-500/30 opacity-0 scale-75 transition-all duration-200">
            <i class="fas fa-check text-[10px]"></i>
        </div>
        <div class="rounded-xl overflow-hidden mb-5 border border-slate-700">
            <div class="h-6 bg-[#161616] flex items-center px-3 gap-2 border-b border-[#393939]">
                <div class="w-3 h-3 rounded bg-[#0f62fe]"></div>
                <div class="w-16 h-1.5 rounded-full bg-[#525252]"></div>
                <div class="ml-auto flex gap-1">
                    <div class="w-1.5 h-1.5 rounded-full bg-[#6f6f6f]"></div>
                    <div class="w-1.5 h-1.5 rounded-full bg-[#6f6f6f]"></div>
                </div>
            </div>
            <div class="p-4 bg-[#161616]">
                <div class="flex gap-3">
                    <div class="w-1/4 h-24 rounded-lg bg-[#262626] shadow-sm border border-[#525252] p-2">
                        <div class="w-3/4 h-2 rounded bg-[#0f62fe] mb-2"></div>
                        <div class="w-full h-1.5 rounded bg-[#393939] mb-1.5"></div>
                        <div class="w-2/3 h-1.5 rounded bg-[#393939]"></div>
                    </div>
                    <div class="w-1/4 h-24 rounded-lg bg-[#262626] shadow-sm border border-[#525252] p-2">
                        <div class="w-3/4 h-2 rounded bg-[#42be65] mb-2"></div>
                        <div class="w-full h-1.5 rounded bg-[#393939] mb-1.5"></div>
                        <div class="w-2/3 h-1.5 rounded bg-[#393939]"></div>
                    </div>
                    <div class="w-1/4 h-24 rounded-lg bg-[#262626] shadow-sm border border-[#525252] p-2">
                        <div class="w-3/4 h-2 rounded bg-[#fa4d56] mb-2"></div>
                        <div class="w-full h-1.5 rounded bg-[#393939] mb-1.5"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
                <i class="fas fa-moon text-blue-600 text-lg"></i>
            </div>
            <div>
                <h3 class="font-semibold text-slate-800">IBM Dark</h3>
                <p class="text-xs text-slate-400 mt-0.5">Carbon g100, IBM Plex, flat blue</p>
            </div>
        </div>
    </button>
</div>

<!-- Info card -->
<div class="mt-10 max-w-3xl">
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-5">
        <div class="flex items-start gap-4">
            <div class="w-9 h-9 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-palette text-indigo-500 text-sm"></i>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-slate-800 mb-1">How it works</h3>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Click a theme card above to switch instantly. Your preference is saved for future visits.
                    The appearance settings are available to all users without special permissions.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
var ALL_THEMES = ['ibm-light', 'ibm-dark'];

function themeCardId(t) {
    return t === 'ibm-light' ? 'themeCardIbmLight' : 'themeCardIbmDark';
}
function themeBadgeId(t) {
    return t === 'ibm-light' ? 'ibmLightActiveBadge' : 'ibmDarkActiveBadge';
}

function highlightActiveCard(theme) {
    ALL_THEMES.forEach(function (t) {
        var badge = document.getElementById(themeBadgeId(t));
        var card = document.getElementById(themeCardId(t));
        if (!badge || !card) return;
        if (t === theme) {
            badge.classList.remove('opacity-0', 'scale-75');
            badge.classList.add('opacity-100', 'scale-100');
            card.classList.add('border-blue-500', 'bg-blue-50/50');
            card.classList.remove('border-slate-200');
        } else {
            badge.classList.add('opacity-0', 'scale-75');
            badge.classList.remove('opacity-100', 'scale-100');
            card.classList.remove('border-blue-500', 'bg-blue-50/50');
            card.classList.add('border-slate-200');
        }
    });
}

function setActiveTheme(theme) {
    document.body.setAttribute('data-theme', theme);

    var icon = document.getElementById('themeIcon');
    var btn = document.getElementById('themeToggle');
    if (icon) {
        icon.className = theme === 'ibm-light' ? 'fas fa-moon' : 'fas fa-sun';
        if (btn) btn.title = theme === 'ibm-light' ? 'Switch to IBM Dark' : 'Switch to IBM Light';
    }

    highlightActiveCard(theme);

    var xhr = new XMLHttpRequest();
    xhr.open('GET', '?set_theme=' + theme, true);
    xhr.send();
}

document.addEventListener('DOMContentLoaded', function () {
    var theme = document.body.getAttribute('data-theme') || 'ibm-light';
    highlightActiveCard(theme);
});
</script>

<?php require_once '../../includes/footer.php'; ?>
