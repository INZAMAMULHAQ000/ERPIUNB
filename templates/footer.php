    </main>
    </div>
    <script>
        // ===== Theme toggle hidden — IBM Light/Dark only =====
        function getTheme() {
            return document.body.getAttribute('data-theme') || 'ibm-light';
        }

        function setTheme(theme) {
            document.body.setAttribute('data-theme', theme);
        }

        // ===== Sidebar Toggle =====
        function toggleSidebar() {
            var sidebar = document.getElementById('sidebar');
            var isMobile = window.innerWidth < 768;

            if (isMobile) {
                sidebar.classList.toggle('open');
                var overlay = document.getElementById('sidebar-overlay');
                if (sidebar.classList.contains('open')) {
                    if (!overlay) {
                        overlay = document.createElement('div');
                        overlay.id = 'sidebar-overlay';
                        overlay.addEventListener('click', function() {
                            toggleSidebar();
                        });
                        document.body.appendChild(overlay);
                    }
                } else {
                    if (overlay) overlay.remove();
                }
            } else {
                sidebar.classList.toggle('collapsed');
                var main = document.querySelector('.erp-main');
                if (main) {
                    main.style.marginLeft = sidebar.classList.contains('collapsed') ? '4rem' : '16rem';
                }
            }
        }

        // Click outside to close mobile sidebar
        document.addEventListener('click', function(e) {
            if (window.innerWidth >= 768) return;
            var sidebar = document.getElementById('sidebar');
            var btn = document.getElementById('sidebarToggleBtn');
            if (sidebar && sidebar.classList.contains('open') &&
                !sidebar.contains(e.target) && btn && !btn.contains(e.target)) {
                sidebar.classList.remove('open');
                var overlay = document.getElementById('sidebar-overlay');
                if (overlay) overlay.remove();
            }
        });

        $(document).ready(function() {
            $('input, textarea, select').attr('autocomplete', 'off');
        });
    </script>
    <?php if (function_exists('ai_render_chat_launcher') && strpos($_SERVER['SCRIPT_NAME'] ?? '', '/modules/ai/') !== false): ?>
        <?php ai_render_chat_launcher(); ?>
    <?php endif; ?>
    </body>

    </html>