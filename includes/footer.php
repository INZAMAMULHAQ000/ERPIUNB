    </main>
    </div>
    <script src="<?= rtrim(BASE_URL, '/') ?>/assets/js/fluent-dropdown.js"></script>
    <script src="<?= rtrim(BASE_URL, '/') ?>/assets/js/carbon-charts.js"></script>
    <script>
        // Theme toggling lives in assets/js/theme.js (loaded from <head>) so the
        // button also works on pages that don't include this footer.

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
                        overlay.addEventListener('click', function () { toggleSidebar(); });
                        document.body.appendChild(overlay);
                    }
                } else {
                    if (overlay) overlay.remove();
                }
            } else {
                sidebar.classList.toggle('collapsed');
            }
        }

        document.addEventListener('click', function (e) {
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

        // ===== Sidebar Dropdown Toggle (accordion: one at a time) =====
        document.addEventListener('DOMContentLoaded', function () {
            var sidebar = document.getElementById('sidebar');
            if (!sidebar) return;
            var titles = sidebar.querySelectorAll('.section-title');

            titles.forEach(function (title) {
                title.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (sidebar.classList.contains('collapsed')) return;

                    var group = this.closest('.group');
                    if (!group) return;

                    var isOpen = group.classList.contains('open');
                    if (isOpen) {
                        // Close this one
                        group.classList.remove('open');
                        this.classList.remove('open');
                    } else {
                        // Close all other groups first
                        var allGroups = sidebar.querySelectorAll('.group.open');
                        allGroups.forEach(function (g) {
                            g.classList.remove('open');
                            var t = g.querySelector('.section-title');
                            if (t) t.classList.remove('open');
                        });
                        // Open clicked one
                        group.classList.add('open');
                        this.classList.add('open');
                        // Scroll into view if needed
                        setTimeout(function () {
                            var rect = group.getBoundingClientRect();
                            var sRect = sidebar.getBoundingClientRect();
                            if (rect.bottom > sRect.bottom) {
                                group.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                            }
                        }, 50);
                    }
                });
            });

            // Auto-open section for current page and mark active link
            var path = window.location.pathname;
            sidebar.querySelectorAll('.group').forEach(function (group) {
                var links = group.querySelectorAll('a');
                var sectionTitle = group.querySelector('.section-title');
                for (var i = 0; i < links.length; i++) {
                    var href = links[i].getAttribute('href');
                    if (href && path.indexOf(href) !== -1) {
                        group.classList.add('open');
                        if (sectionTitle) sectionTitle.classList.add('open', 'has-active');
                        links[i].classList.add('active');
                        break;
                    }
                }
            });
            // Mark top-level links (non-dropdown) as active
            sidebar.querySelectorAll('a').forEach(function (link) {
                if (link.closest('.dropdown-items')) return;
                var href = link.getAttribute('href');
                if (href && path.indexOf(href) !== -1) {
                    link.classList.add('active');
                }
            });
        });
    </script>
    <?php if (function_exists('ai_render_chat_launcher') && strpos($_SERVER['SCRIPT_NAME'] ?? '', '/modules/ai/') !== false): ?>
        <?php ai_render_chat_launcher(); ?>
    <?php endif; ?>

    <!-- ===== CLIENT PREVIEW MOCK — START (remove this block to peel off; also revert the button in templates/navbar.php and delete templates/mock_preview.php) ===== -->
    <?php require_once dirname(__DIR__) . '/templates/mock_preview.php'; ?>
    <!-- ===== CLIENT PREVIEW MOCK — END ===== -->
</body>
</html>
<?php ob_end_flush(); ?>
