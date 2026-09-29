<nav class="erp-navbar">
    <div class="px-4">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center gap-2">
                <button onclick="toggleSidebar()" class="navbar-toggle-btn" id="sidebarToggleBtn" title="Toggle sidebar">
                    <i class="fas fa-bars"></i>
                </button>
                <a href="<?= url('index.php') ?>" class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-600 to-indigo-800 flex items-center justify-center shadow-md shadow-indigo-500/30">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7" rx="1.5" />
                            <rect x="14" y="3" width="7" height="7" rx="1.5" />
                            <rect x="3" y="14" width="7" height="7" rx="1.5" />
                            <rect x="14" y="14" width="7" height="7" rx="1.5" />
                            <path d="M6.5 10h11M10 6.5v11" />
                        </svg>
                    </span>
                    <span class="text-lg font-bold text-slate-800 tracking-tight">ERP System</span>
                </a>
            </div>
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2.5 pr-3 border-r border-slate-200">
                    <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 text-sm font-semibold">
                        <?= strtoupper(substr(htmlspecialchars($_SESSION['user_name'] ?? 'U'), 0, 1)) ?>
                    </div>
                    <span class="text-sm font-medium text-slate-700"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
                </div>
                <!-- Currency Switcher -->
                <div class="relative group pr-3 border-r border-slate-200">
                    <button class="flex items-center gap-1.5 px-2.5 py-2 text-sm font-medium text-slate-600 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition">
                        <span><?php $cur = getCurrencies()[getActiveCurrency()];
                                echo $cur['symbol']; ?></span>
                        <span class="hidden sm:inline text-xs uppercase font-semibold"><?= getActiveCurrency() ?></span>
                        <i class="fas fa-chevron-down text-[10px] text-slate-400"></i>
                    </button>
                    <div class="absolute right-0 mt-1 w-50 max-h-64 overflow-y-auto bg-white rounded-xl shadow-xl border border-slate-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-150 z-50">
                        <div class="py-1.5">
                            <?php foreach (getCurrencies() as $code => $info): ?>
                                <a href="?set_currency=<?= $code ?>"
                                    class="flex items-center gap-3 px-4 py-2 text-sm <?= getActiveCurrency() === $code ? 'text-indigo-600 bg-indigo-50 font-medium' : 'text-slate-600 hover:bg-slate-50' ?> transition">

                                    <span class="w-6 text-center">
                                        <?= $info['symbol'] ?>
                                    </span>

                                    <span class="flex-1">
                                        <?= $info['name'] ?>
                                    </span>

                                    <span class="text-xs font-semibold <?= getActiveCurrency() === $code ? 'text-indigo-500' : 'text-slate-400' ?>">
                                        <?= $code ?>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <!-- ===== CLIENT PREVIEW MOCK — START (remove this whole block to peel off) ===== -->
                <button onclick="toggleMockPreview()" id="mockPreviewToggle"
                    class="mock-preview-toggle flex items-center justify-center w-9 h-9 text-sm rounded-lg text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 transition"
                    title="New Navbar Preview (Client Review)">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                </button>
                <!-- ===== CLIENT PREVIEW MOCK — END ===== -->
                <button onclick="toggleIBMTheme()" id="themeToggle"
                    class="flex items-center justify-center w-9 h-9 text-sm rounded-lg text-slate-500 hover:text-blue-600 hover:bg-blue-50 transition"
                    title="Switch to IBM Dark">
                    <i id="themeIcon" class="fas fa-moon"></i>
                </button>
                <a href="<?= url('modules/auth/logout.php') ?>" class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-slate-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Sign Out">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="hidden sm:inline">Sign Out</span>
                </a>
            </div>
        </div>
    </div>
</nav>