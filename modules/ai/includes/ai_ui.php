<?php
/**
 * Shared AI module UI helpers.
 *
 * Provides a reusable shell for AI hub cards, top navigation, and the
 * assistant popup launcher used across AI module screens.
 */

if (!function_exists('ai_get_user_permissions')) {
    function ai_get_user_permissions($db, int $userId): array
    {
        if (!$db || $userId <= 0) {
            return [];
        }

        $stmt = $db->prepare("
            SELECT DISTINCT CONCAT(p.module, '.', p.name) AS perm
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            JOIN user_roles ur ON rp.role_id = ur.role_id
            WHERE ur.user_id = ?
        ");
        $stmt->execute([$userId]);

        return array_values(array_filter(array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'perm')));
    }
}

if (!function_exists('ai_get_accessible_ai_destinations')) {
    function ai_get_accessible_ai_destinations($db, int $userId): array
    {
        if (!class_exists('AINavigationRegistry')) {
            return [];
        }

        if (($_SESSION['role'] ?? '') === 'superadmin') {
            return array_values(array_filter(AINavigationRegistry::getAll(), static function (array $dest): bool {
                return ($dest['module'] ?? '') === 'ai' && !empty($dest['active']);
            }));
        }

        $permissions = ai_get_user_permissions($db, $userId);
        $destinations = AINavigationRegistry::getAccessible($permissions);

        return array_values(array_filter($destinations, static function (array $dest): bool {
            return ($dest['module'] ?? '') === 'ai' && !empty($dest['active']);
        }));
    }
}

if (!function_exists('ai_group_destinations_for_hub')) {
    function ai_group_destinations_for_hub(array $destinations): array
    {
        $sections = [
            'conversation' => [
                'label' => 'Conversation',
                'icon' => 'fas fa-comments',
                'gradient' => 'linear-gradient(135deg, #0f172a 0%, #123456 45%, #1f6f8b 100%)',
                'ids' => ['ai.assistant', 'ai.copilot', 'ai.command_center', 'ai.voice', 'ai.tasks'],
            ],
            'build' => [
                'label' => 'Build & Automate',
                'icon' => 'fas fa-sitemap',
                'gradient' => 'linear-gradient(135deg, #1e1b4b 0%, #3730a3 45%, #2563eb 100%)',
                'ids' => ['ai.workflows', 'ai.automation', 'ai.automations', 'ai.process_intelligence', 'ai.tools', 'ai.integrations', 'ai.agents'],
            ],
            'knowledge' => [
                'label' => 'Knowledge & Collaboration',
                'icon' => 'fas fa-book-open',
                'gradient' => 'linear-gradient(135deg, #312e81 0%, #7c3aed 45%, #d946ef 100%)',
                'ids' => ['ai.knowledge', 'ai.documents', 'ai.communications', 'ai.workspaces', 'ai.skills', 'ai.insights', 'ai.analytics'],
            ],
            'governance' => [
                'label' => 'Governance & Monitoring',
                'icon' => 'fas fa-shield-halved',
                'gradient' => 'linear-gradient(135deg, #0f766e 0%, #14b8a6 45%, #38bdf8 100%)',
                'ids' => ['ai.management', 'ai.observability', 'ai.monitoring', 'ai.settings'],
            ],
            'platform' => [
                'label' => 'Platform & Models',
                'icon' => 'fas fa-cubes',
                'gradient' => 'linear-gradient(135deg, #3f3f46 0%, #52525b 45%, #0f62fe 100%)',
                'ids' => ['ai.providers', 'ai.models', 'ai.intelligence'],
            ],
        ];

        $lookup = [];
        foreach ($destinations as $dest) {
            $lookup[$dest['id']] = $dest;
        }

        $grouped = [];
        foreach ($sections as $key => $section) {
            $items = [];
            foreach ($section['ids'] as $id) {
                if (!isset($lookup[$id])) {
                    continue;
                }
                $items[] = $lookup[$id];
                unset($lookup[$id]);
            }
            if (!empty($items)) {
                $grouped[] = [
                    'key' => $key,
                    'label' => $section['label'],
                    'icon' => $section['icon'],
                    'gradient' => $section['gradient'],
                    'items' => $items,
                ];
            }
        }

        if (!empty($lookup)) {
            $grouped[] = [
                'key' => 'more',
                'label' => 'More AI Pages',
                'icon' => 'fas fa-layer-group',
                'gradient' => 'linear-gradient(135deg, #111827 0%, #374151 55%, #4b5563 100%)',
                'items' => array_values($lookup),
            ];
        }

        return $grouped;
    }
}

if (!function_exists('ai_render_shell_styles')) {
    function ai_render_shell_styles(): void
    {
        static $rendered = false;
        if ($rendered) {
            return;
        }
        $rendered = true;
        ?>
        <style>
            .ai-shell {
                --ai-bg: radial-gradient(circle at top left, rgba(59, 130, 246, 0.18), transparent 32%),
                    radial-gradient(circle at top right, rgba(45, 212, 191, 0.14), transparent 28%),
                    linear-gradient(180deg, #0b1220 0%, #0f172a 30%, #111827 100%);
                --ai-card: rgba(15, 23, 42, 0.72);
                --ai-card-border: rgba(148, 163, 184, 0.18);
                --ai-text: #e2e8f0;
                --ai-muted: #94a3b8;
                --ai-soft: rgba(255, 255, 255, 0.05);
                --ai-accent: #38bdf8;
                --ai-accent-2: #8b5cf6;
            }

            .ai-shell-banner {
                position: relative;
                overflow: hidden;
                border-radius: 1.5rem;
                border: 1px solid rgba(148, 163, 184, 0.20);
                background: var(--ai-bg);
                box-shadow: 0 24px 80px rgba(15, 23, 42, 0.18);
            }

            .ai-shell-banner::before {
                content: '';
                position: absolute;
                inset: 0;
                background:
                    linear-gradient(135deg, rgba(255,255,255,0.08) 0%, transparent 34%),
                    radial-gradient(circle at 20% 20%, rgba(56, 189, 248, 0.18), transparent 20%),
                    radial-gradient(circle at 80% 10%, rgba(139, 92, 246, 0.18), transparent 18%);
                pointer-events: none;
            }

            .ai-shell-banner > * {
                position: relative;
                z-index: 1;
            }

            .ai-shell-title {
                color: #f8fafc;
                letter-spacing: -0.03em;
            }

            .ai-shell-subtitle {
                color: rgba(226, 232, 240, 0.78);
            }

            .ai-shell-nav {
                display: flex;
                gap: 0.65rem;
                overflow-x: auto;
                padding: 0.15rem 0 0.35rem;
                scrollbar-width: thin;
            }

            .ai-shell-nav::-webkit-scrollbar {
                height: 8px;
            }

            .ai-shell-nav::-webkit-scrollbar-thumb {
                background: rgba(148, 163, 184, 0.35);
                border-radius: 999px;
            }

            .ai-shell-nav-link {
                display: inline-flex;
                align-items: center;
                gap: 0.55rem;
                white-space: nowrap;
                padding: 0.68rem 0.95rem;
                border-radius: 999px;
                border: 1px solid rgba(148, 163, 184, 0.22);
                background: rgba(15, 23, 42, 0.55);
                color: #cbd5e1;
                text-decoration: none;
                font-size: 0.875rem;
                font-weight: 600;
                transition: transform 160ms ease, background 160ms ease, color 160ms ease, border-color 160ms ease;
            }

            .ai-shell-nav-link:hover {
                transform: translateY(-1px);
                color: #fff;
                background: rgba(30, 41, 59, 0.88);
                border-color: rgba(56, 189, 248, 0.40);
            }

            .ai-shell-nav-link.active {
                color: #fff;
                background: linear-gradient(135deg, rgba(14, 165, 233, 0.92), rgba(139, 92, 246, 0.92));
                border-color: transparent;
                box-shadow: 0 14px 30px rgba(56, 189, 248, 0.22);
            }

            .ai-shell-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 1rem;
            }

            .ai-shell-card {
                position: relative;
                display: flex;
                flex-direction: column;
                gap: 0.95rem;
                min-height: 170px;
                padding: 1.15rem;
                border-radius: 1.25rem;
                border: 1px solid rgba(148, 163, 184, 0.18);
                background:
                    linear-gradient(180deg, rgba(15, 23, 42, 0.90), rgba(15, 23, 42, 0.78)),
                    linear-gradient(135deg, rgba(59, 130, 246, 0.08), rgba(139, 92, 246, 0.04));
                color: #e2e8f0;
                text-decoration: none;
                overflow: hidden;
                transition: transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease;
                box-shadow: 0 18px 46px rgba(15, 23, 42, 0.16);
            }

            .ai-shell-card:hover {
                transform: translateY(-3px);
                border-color: rgba(56, 189, 248, 0.42);
                box-shadow: 0 26px 60px rgba(15, 23, 42, 0.22);
            }

            .ai-shell-card::before {
                content: '';
                position: absolute;
                inset: 0;
                background: radial-gradient(circle at 0% 0%, rgba(255, 255, 255, 0.10), transparent 32%);
                pointer-events: none;
            }

            .ai-shell-card-head {
                display: flex;
                align-items: start;
                justify-content: space-between;
                gap: 0.75rem;
                position: relative;
                z-index: 1;
            }

            .ai-shell-card-icon {
                width: 2.75rem;
                height: 2.75rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 0.9rem;
                color: #fff;
                background: rgba(255, 255, 255, 0.10);
                border: 1px solid rgba(255, 255, 255, 0.12);
                flex-shrink: 0;
            }

            .ai-shell-card-title {
                font-size: 1rem;
                font-weight: 700;
                letter-spacing: -0.02em;
                color: #fff;
            }

            .ai-shell-card-desc {
                color: rgba(226, 232, 240, 0.76);
                font-size: 0.875rem;
                line-height: 1.45;
            }

            .ai-shell-chip {
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
                padding: 0.35rem 0.65rem;
                border-radius: 999px;
                font-size: 0.72rem;
                font-weight: 700;
                letter-spacing: 0.06em;
                text-transform: uppercase;
                color: #bfdbfe;
                background: rgba(56, 189, 248, 0.10);
                border: 1px solid rgba(56, 189, 248, 0.20);
            }

            .ai-shell-launcher {
                position: fixed;
                right: 1.2rem;
                bottom: 1.2rem;
                z-index: 70;
            }

            .ai-shell-launcher-button {
                width: 3.55rem;
                height: 3.55rem;
                border-radius: 999px;
                border: none;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: linear-gradient(135deg, #0ea5e9, #8b5cf6);
                color: #fff;
                box-shadow: 0 18px 44px rgba(14, 165, 233, 0.34);
                transition: transform 180ms ease, box-shadow 180ms ease;
            }

            .ai-shell-launcher-button:hover {
                transform: translateY(-2px) scale(1.02);
                box-shadow: 0 22px 50px rgba(14, 165, 233, 0.42);
            }

            .ai-shell-launcher-label {
                display: inline-flex;
                align-items: center;
                margin-right: 0.65rem;
                padding: 0.55rem 0.85rem;
                border-radius: 999px;
                background: rgba(15, 23, 42, 0.90);
                color: #e2e8f0;
                border: 1px solid rgba(148, 163, 184, 0.20);
                box-shadow: 0 18px 32px rgba(15, 23, 42, 0.18);
                font-size: 0.85rem;
                font-weight: 600;
            }

            .ai-shell-modal {
                position: fixed;
                inset: 0;
                z-index: 80;
                display: none;
                align-items: center;
                justify-content: center;
                padding: 1rem;
                background: rgba(2, 6, 23, 0.68);
                backdrop-filter: blur(12px);
            }

            .ai-shell-modal.open {
                display: flex;
            }

            .ai-shell-panel {
                width: min(1180px, calc(100vw - 2rem));
                height: min(86vh, 920px);
                border-radius: 1.35rem;
                overflow: hidden;
                background: #0b1220;
                border: 1px solid rgba(148, 163, 184, 0.20);
                box-shadow: 0 28px 90px rgba(2, 6, 23, 0.55);
                display: flex;
                flex-direction: column;
            }

            .ai-shell-panel-bar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                padding: 0.95rem 1.1rem;
                border-bottom: 1px solid rgba(148, 163, 184, 0.14);
                background: linear-gradient(135deg, rgba(15, 23, 42, 0.95), rgba(30, 41, 59, 0.92));
                color: #e2e8f0;
            }

            .ai-shell-panel-close {
                border: 1px solid rgba(148, 163, 184, 0.20);
                background: rgba(255, 255, 255, 0.04);
                color: #e2e8f0;
                border-radius: 999px;
                width: 2.4rem;
                height: 2.4rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            .ai-shell-panel-frame {
                width: 100%;
                flex: 1;
                border: 0;
                background: #fff;
            }

            @media (max-width: 640px) {
                .ai-shell-launcher {
                    right: 0.85rem;
                    bottom: 0.85rem;
                }

                .ai-shell-panel {
                    width: calc(100vw - 1rem);
                    height: calc(100vh - 1rem);
                    border-radius: 1rem;
                }

                .ai-shell-launcher-label {
                    display: none;
                }
            }

            /* ------------------------------------------------------------
               AI standalone theme bridge
               Apply to pages that render their own HTML/CSS and set the
               `ai-module-page` body class.
               ------------------------------------------------------------ */
            body.ai-module-page {
                background: var(--bg-primary) !important;
                color: var(--text-primary) !important;
            }

            body.ai-module-page[data-theme="ibm-light"] {
                --bg-primary: #f4f4f4;
                --bg-secondary: #ffffff;
                --bg-card: #ffffff;
                --bg-input: #f4f4f4;
                --border: #e0e0e0;
                --text-primary: #161616;
                --text-secondary: #525252;
                --text-muted: #8d8d8d;
                --accent: #0f62fe;
                --accent-hover: #0043ce;
                --green: #24a148;
                --red: #da1e28;
                --yellow: #f1c21b;
                --orange: #ff832b;
                --blue: #0f62fe;
                --purple: #8a3ffc;
            }

            body.ai-module-page[data-theme="ibm-dark"] {
                --bg-primary: #161616;
                --bg-secondary: #262626;
                --bg-card: #262626;
                --bg-input: #393939;
                --border: #393939;
                --text-primary: #f4f4f4;
                --text-secondary: #c6c6c6;
                --text-muted: #a8a8a8;
                --accent: #0f62fe;
                --accent-hover: #78a9ff;
                --green: #42be65;
                --red: #fa4d56;
                --yellow: #f1c21b;
                --orange: #ff832b;
                --blue: #4589ff;
                --purple: #a56eff;
            }

            body.ai-module-page .header,
            body.ai-module-page .card,
            body.ai-module-page .panel,
            body.ai-module-page .kpi-card,
            body.ai-module-page .stat-card,
            body.ai-module-page .result-card,
            body.ai-module-page .briefing-item,
            body.ai-module-page .list-item,
            body.ai-module-page .forecast-details,
            body.ai-module-page .modal-card,
            body.ai-module-page .empty-state,
            body.ai-module-page .result-box {
                background: var(--bg-card) !important;
                color: var(--text-primary) !important;
                border-color: var(--border) !important;
                box-shadow: none !important;
            }

            body.ai-module-page .tabs {
                background: var(--bg-secondary) !important;
                border-color: var(--border) !important;
            }

            body.ai-module-page .tab {
                color: var(--text-secondary) !important;
                border-bottom-color: transparent !important;
            }

            body.ai-module-page .tab.active {
                color: var(--accent) !important;
                border-bottom-color: var(--accent) !important;
            }

            body.ai-module-page .form-control,
            body.ai-module-page input,
            body.ai-module-page select,
            body.ai-module-page textarea {
                background: var(--bg-input) !important;
                color: var(--text-primary) !important;
                border-color: var(--border) !important;
            }

            body.ai-module-page .btn,
            body.ai-module-page button {
                color: inherit;
            }

            body.ai-module-page .btn-primary,
            body.ai-module-page .chat-input button,
            body.ai-module-page .bg-blue-600,
            body.ai-module-page .bg-indigo-600,
            body.ai-module-page .bg-emerald-600,
            body.ai-module-page .bg-purple-600 {
                background: linear-gradient(135deg, var(--accent), var(--accent-hover)) !important;
                color: #fff !important;
                border-color: transparent !important;
            }

            body.ai-module-page .btn-secondary,
            body.ai-module-page .quick-query,
            body.ai-module-page .bg-white,
            body.ai-module-page .bg-gray-50,
            body.ai-module-page .bg-slate-50 {
                background: var(--bg-card) !important;
                color: var(--text-primary) !important;
                border-color: var(--border) !important;
            }

            body.ai-module-page .bg-gray-100,
            body.ai-module-page .bg-slate-100 {
                background: rgba(148, 163, 184, 0.14) !important;
            }

            body.ai-module-page .bg-gray-200,
            body.ai-module-page .bg-slate-200 {
                background: rgba(148, 163, 184, 0.20) !important;
            }

            body.ai-module-page .bg-gray-300,
            body.ai-module-page .bg-slate-300 {
                background: rgba(148, 163, 184, 0.28) !important;
            }

            body.ai-module-page .text-gray-800,
            body.ai-module-page .text-gray-900,
            body.ai-module-page .text-slate-800,
            body.ai-module-page .text-slate-900 {
                color: var(--text-primary) !important;
            }

            body.ai-module-page .text-gray-700,
            body.ai-module-page .text-slate-700 {
                color: var(--text-primary) !important;
            }

            body.ai-module-page .text-gray-600,
            body.ai-module-page .text-gray-500,
            body.ai-module-page .text-slate-600,
            body.ai-module-page .text-slate-500 {
                color: var(--text-secondary) !important;
            }

            body.ai-module-page .text-gray-400,
            body.ai-module-page .text-slate-400,
            body.ai-module-page .text-gray-300,
            body.ai-module-page .text-slate-300 {
                color: var(--text-muted) !important;
            }

            body.ai-module-page .border-gray-100,
            body.ai-module-page .border-gray-200,
            body.ai-module-page .border-slate-100,
            body.ai-module-page .border-slate-200 {
                border-color: var(--border) !important;
            }

            body.ai-module-page .border-gray-300,
            body.ai-module-page .border-slate-300 {
                border-color: var(--border) !important;
            }

            body.ai-module-page .quick-query:hover,
            body.ai-module-page .bg-white:hover,
            body.ai-module-page .bg-gray-50:hover,
            body.ai-module-page .bg-slate-50:hover {
                border-color: var(--accent) !important;
            }

            body.ai-module-page .toast {
                background: var(--bg-card) !important;
                color: var(--text-primary) !important;
                border-color: var(--border) !important;
            }
        </style>
        <?php
    }
}
if (!function_exists('ai_render_page_styles')) {
    function ai_render_page_styles(): void
    {
        static $rendered = false;
        if ($rendered) {
            return;
        }
        $rendered = true;
        ?>
        <style>
            /* ================================================================
               AI Unified Module Page Styles
               Scoped to body.ai-module-page — does not affect other modules.
               ================================================================ */

            /* --- CSS Custom Properties (AI Dark Theme) --- */
            body.ai-module-page {
                --ai-page-bg: #0b1220;
                --ai-page-bg-gradient: radial-gradient(circle at top left, rgba(56, 189, 248, 0.12) 0%, transparent 45%),
                    radial-gradient(circle at bottom right, rgba(139, 92, 246, 0.10) 0%, transparent 40%),
                    linear-gradient(180deg, #0b1220 0%, #0f172a 40%, #111827 100%);
                --ai-card-bg: rgba(15, 23, 42, 0.82);
                --ai-card-border: rgba(148, 163, 184, 0.18);
                --ai-card-shadow: 0 8px 32px rgba(15, 23, 42, 0.25), 0 2px 8px rgba(0, 0, 0, 0.18);
                --ai-card-hover-shadow: 0 16px 48px rgba(56, 189, 248, 0.10), 0 4px 16px rgba(0, 0, 0, 0.22);
                --ai-text-primary: #f1f5f9;
                --ai-text-secondary: #cbd5e1;
                --ai-text-muted: #94a3b8;
                --ai-accent: #38bdf8;
                --ai-accent-glow: rgba(56, 189, 248, 0.35);
                --ai-accent-2: #8b5cf6;
                --ai-accent-2-glow: rgba(139, 92, 246, 0.30);
                --ai-success: #34d399;
                --ai-warning: #fbbf24;
                --ai-danger: #f87171;
                --ai-border: rgba(148, 163, 184, 0.18);
                --ai-input-bg: rgba(15, 23, 42, 0.70);
                --ai-input-border: rgba(148, 163, 184, 0.24);
                --ai-input-focus-border: var(--ai-accent);
                --ai-tab-bg: rgba(15, 23, 42, 0.55);
                --ai-tab-active-bg: rgba(56, 189, 248, 0.12);
                --ai-badge-success-bg: rgba(52, 211, 153, 0.15);
                --ai-badge-warning-bg: rgba(251, 191, 36, 0.15);
                --ai-badge-danger-bg: rgba(248, 113, 113, 0.15);
                --ai-badge-info-bg: rgba(56, 189, 248, 0.15);
                --ai-badge-purple-bg: rgba(139, 92, 246, 0.15);
            }

            /* --- Light Theme Overrides --- */
            body.ai-module-page[data-theme="ibm-light"] {
                --ai-page-bg: #f4f4f4;
                --ai-page-bg-gradient: linear-gradient(180deg, #f4f4f4 0%, #eef0f3 100%);
                --ai-card-bg: #ffffff;
                --ai-card-border: #e2e8f0;
                --ai-card-shadow: 0 2px 12px rgba(0, 0, 0, 0.06), 0 0 0 1px rgba(0, 0, 0, 0.04);
                --ai-card-hover-shadow: 0 8px 24px rgba(0, 0, 0, 0.10), 0 0 0 1px rgba(56, 189, 248, 0.25);
                --ai-text-primary: #0f172a;
                --ai-text-secondary: #475569;
                --ai-text-muted: #94a3b8;
                --ai-accent: #0f62fe;
                --ai-accent-glow: rgba(15, 98, 254, 0.18);
                --ai-accent-2: #7c3aed;
                --ai-accent-2-glow: rgba(124, 58, 237, 0.15);
                --ai-success: #16a34a;
                --ai-warning: #d97706;
                --ai-danger: #dc2626;
                --ai-border: #e2e8f0;
                --ai-input-bg: #f8fafc;
                --ai-input-border: #cbd5e1;
                --ai-input-focus-border: var(--ai-accent);
                --ai-tab-bg: #f1f5f9;
                --ai-tab-active-bg: rgba(15, 98, 254, 0.07);
                --ai-badge-success-bg: #dcfce7;
                --ai-badge-warning-bg: #fef3c7;
                --ai-badge-danger-bg: #fee2e2;
                --ai-badge-info-bg: #e0f2fe;
                --ai-badge-purple-bg: #f3e8ff;
            }
            /* ================================================================
               1. Page Container
               ================================================================ */
            body.ai-module-page .ai-page-container {
                min-height: 100vh;
                padding: 1.5rem 2rem 3rem;
                background: var(--ai-page-bg-gradient);
                background-attachment: fixed;
                color: var(--ai-text-primary);
                font-family: "Inter", "IBM Plex Sans", system-ui, -apple-system, sans-serif;
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
            }

            /* ================================================================
               2. Page Header (Hero)
               ================================================================ */
            body.ai-module-page .ai-page-header {
                position: relative;
                overflow: hidden;
                border-radius: 1.25rem;
                padding: 2.5rem 2.5rem 2rem;
                margin-bottom: 1.75rem;
                background: linear-gradient(135deg, rgba(15, 23, 42, 0.90) 0%, rgba(30, 41, 59, 0.85) 100%);
                border: 1px solid var(--ai-card-border);
                box-shadow: var(--ai-card-shadow);
                isolation: isolate;
            }

            body.ai-module-page .ai-page-header::before {
                content: "";
                position: absolute;
                inset: 0;
                background:
                    linear-gradient(135deg, rgba(255, 255, 255, 0.06) 0%, transparent 40%),
                    radial-gradient(circle at 15% 25%, var(--ai-accent-glow), transparent 50%),
                    radial-gradient(circle at 85% 75%, var(--ai-accent-2-glow), transparent 50%);
                pointer-events: none;
                z-index: 0;
            }

            body.ai-module-page .ai-page-header > * {
                position: relative;
                z-index: 1;
            }

            body.ai-module-page[data-theme="ibm-light"] .ai-page-header {
                background: linear-gradient(135deg, #ffffff 0%, #f1f5f9 100%);
            }

            /* ================================================================
               3. Page Title & Subtitle
               ================================================================ */
            body.ai-module-page .ai-page-title {
                font-size: 1.875rem;
                font-weight: 700;
                letter-spacing: -0.025em;
                line-height: 1.2;
                color: #f8fafc;
                margin: 0 0 0.35rem;
                display: flex;
                align-items: center;
                gap: 0.75rem;
            }

            body.ai-module-page .ai-page-title .ai-page-icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 2.5rem;
                height: 2.5rem;
                border-radius: 0.65rem;
                background: linear-gradient(135deg, var(--ai-accent), var(--ai-accent-2));
                color: #fff;
                font-size: 1.1rem;
                flex-shrink: 0;
                box-shadow: 0 4px 14px var(--ai-accent-glow);
            }

            body.ai-module-page .ai-page-subtitle {
                font-size: 0.95rem;
                font-weight: 400;
                color: var(--ai-text-muted);
                margin: 0;
                max-width: 60ch;
                line-height: 1.55;
            }

            body.ai-module-page[data-theme="ibm-light"] .ai-page-title {
                color: #0f172a;
            }
            /* ================================================================
               4. Content Card (Glass Morphism)
               ================================================================ */
            body.ai-module-page .ai-content-card {
                background: var(--ai-card-bg);
                border: 1px solid var(--ai-card-border);
                border-radius: 1rem;
                padding: 1.5rem 1.75rem;
                box-shadow: var(--ai-card-shadow);
                backdrop-filter: blur(14px);
                -webkit-backdrop-filter: blur(14px);
                transition: box-shadow 0.25s ease, border-color 0.25s ease, transform 0.18s ease;
            }

            body.ai-module-page .ai-content-card:hover {
                box-shadow: var(--ai-card-hover-shadow);
                border-color: rgba(56, 189, 248, 0.30);
            }

            body.ai-module-page[data-theme="ibm-light"] .ai-content-card {
                backdrop-filter: none;
                -webkit-backdrop-filter: none;
            }

            /* ================================================================
               5. Sections
               ================================================================ */
            body.ai-module-page .ai-section {
                margin-bottom: 1.75rem;
            }

            body.ai-module-page .ai-section:last-child {
                margin-bottom: 0;
            }

            body.ai-module-page .ai-section-title {
                display: flex;
                align-items: center;
                gap: 0.6rem;
                font-size: 1rem;
                font-weight: 600;
                color: var(--ai-text-primary);
                margin: 0 0 1rem;
                padding-bottom: 0.6rem;
                border-bottom: 1px solid var(--ai-border);
                letter-spacing: 0.01em;
            }

            body.ai-module-page .ai-section-title i {
                color: var(--ai-accent);
                font-size: 1rem;
                opacity: 0.85;
            }

            /* ================================================================
               6. Stat / KPI Cards
               ================================================================ */
            body.ai-module-page .ai-stat-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 1.25rem;
            }

            body.ai-module-page .ai-stat-card {
                position: relative;
                overflow: hidden;
                background: var(--ai-card-bg);
                border: 1px solid var(--ai-card-border);
                border-radius: 0.875rem;
                padding: 1.25rem 1.5rem;
                box-shadow: var(--ai-card-shadow);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                transition: transform 0.2s ease, box-shadow 0.25s ease, border-color 0.25s ease;
            }

            body.ai-module-page .ai-stat-card:hover {
                transform: translateY(-2px);
                box-shadow: var(--ai-card-hover-shadow);
                border-color: rgba(56, 189, 248, 0.30);
            }

            body.ai-module-page .ai-stat-card .ai-stat-icon-wrap {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 2.25rem;
                height: 2.25rem;
                border-radius: 0.6rem;
                margin-bottom: 0.75rem;
                font-size: 1rem;
                background: linear-gradient(135deg, var(--ai-accent), var(--ai-accent-2));
                color: #fff;
                box-shadow: 0 3px 10px var(--ai-accent-glow);
            }

            body.ai-module-page .ai-stat-card .ai-stat-value {
                font-size: 1.75rem;
                font-weight: 700;
                letter-spacing: -0.02em;
                line-height: 1;
                color: var(--ai-text-primary);
                margin-bottom: 0.25rem;
            }

            body.ai-module-page .ai-stat-card .ai-stat-label {
                font-size: 0.8rem;
                font-weight: 500;
                text-transform: uppercase;
                letter-spacing: 0.06em;
                color: var(--ai-text-muted);
            }

            body.ai-module-page[data-theme="ibm-light"] .ai-stat-card {
                backdrop-filter: none;
                -webkit-backdrop-filter: none;
            }
            /* ================================================================
               7. Data Table
               ================================================================ */
            body.ai-module-page .ai-data-table {
                width: 100%;
                border-collapse: separate;
                border-spacing: 0;
                font-size: 0.9rem;
            }

            body.ai-module-page .ai-data-table thead th {
                position: sticky;
                top: 0;
                background: var(--ai-card-bg);
                color: var(--ai-text-muted);
                font-weight: 600;
                font-size: 0.75rem;
                text-transform: uppercase;
                letter-spacing: 0.07em;
                padding: 0.85rem 1rem;
                border-bottom: 1px solid var(--ai-border);
                text-align: left;
                white-space: nowrap;
                backdrop-filter: blur(8px);
                -webkit-backdrop-filter: blur(8px);
            }

            body.ai-module-page .ai-data-table tbody tr {
                transition: background 0.18s ease;
            }

            body.ai-module-page .ai-data-table tbody tr:hover {
                background: rgba(56, 189, 248, 0.05);
            }

            body.ai-module-page .ai-data-table tbody td {
                padding: 0.8rem 1rem;
                border-bottom: 1px solid var(--ai-border);
                color: var(--ai-text-secondary);
                vertical-align: middle;
            }

            body.ai-module-page .ai-data-table tbody tr:last-child td {
                border-bottom: none;
            }

            body.ai-module-page .ai-table-wrapper {
                overflow-x: auto;
                border-radius: 0.75rem;
                border: 1px solid var(--ai-card-border);
                box-shadow: var(--ai-card-shadow);
            }

            body.ai-module-page .ai-table-wrapper .ai-data-table {
                border: none;
                box-shadow: none;
            }

            /* ================================================================
               8. Tab Navigation
               ================================================================ */
            body.ai-module-page .ai-tabs {
                display: flex;
                flex-wrap: wrap;
                gap: 0.25rem;
                padding: 0.4rem;
                background: var(--ai-tab-bg);
                border-radius: 0.85rem;
                border: 1px solid var(--ai-border);
                margin-bottom: 1.5rem;
            }

            body.ai-module-page .ai-tab {
                flex: 1 1 auto;
                min-width: 7rem;
                text-align: center;
                padding: 0.55rem 0.9rem;
                border-radius: 0.6rem;
                font-size: 0.875rem;
                font-weight: 500;
                color: var(--ai-text-muted);
                cursor: pointer;
                border: none;
                background: transparent;
                transition: background 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
                text-decoration: none;
                white-space: nowrap;
            }

            body.ai-module-page .ai-tab:hover {
                color: var(--ai-text-primary);
                background: rgba(148, 163, 184, 0.10);
            }

            body.ai-module-page .ai-tab.active {
                background: var(--ai-tab-active-bg);
                color: var(--ai-accent);
                font-weight: 600;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
            }

            body.ai-module-page .ai-tab i {
                margin-right: 0.4rem;
                font-size: 0.8rem;
            }
            /* ================================================================
               9. Alerts / Badges
               ================================================================ */
            body.ai-module-page .ai-alert {
                display: flex;
                align-items: flex-start;
                gap: 0.75rem;
                padding: 0.9rem 1.1rem;
                border-radius: 0.7rem;
                font-size: 0.9rem;
                line-height: 1.5;
                margin-bottom: 0.9rem;
                border: 1px solid transparent;
            }

            body.ai-module-page .ai-alert i {
                margin-top: 0.15rem;
                flex-shrink: 0;
            }

            body.ai-module-page .ai-alert-info {
                background: var(--ai-badge-info-bg);
                color: #0284c7;
                border-color: rgba(56, 189, 248, 0.25);
            }

            body.ai-module-page[data-theme="ibm-light"] .ai-alert-info {
                color: #0369a1;
            }

            body.ai-module-page .ai-alert-success {
                background: var(--ai-badge-success-bg);
                color: #059669;
                border-color: rgba(52, 211, 153, 0.25);
            }

            body.ai-module-page[data-theme="ibm-light"] .ai-alert-success {
                color: #15803d;
            }

            body.ai-module-page .ai-alert-warning {
                background: var(--ai-badge-warning-bg);
                color: #b45309;
                border-color: rgba(251, 191, 36, 0.30);
            }

            body.ai-module-page[data-theme="ibm-light"] .ai-alert-warning {
                color: #92400e;
            }

            body.ai-module-page .ai-alert-danger {
                background: var(--ai-badge-danger-bg);
                color: #dc2626;
                border-color: rgba(248, 113, 113, 0.25);
            }

            body.ai-module-page[data-theme="ibm-light"] .ai-alert-danger {
                color: #991b1b;
            }

            body.ai-module-page .ai-badge {
                display: inline-flex;
                align-items: center;
                gap: 0.3rem;
                padding: 0.2rem 0.65rem;
                border-radius: 9999px;
                font-size: 0.75rem;
                font-weight: 600;
                letter-spacing: 0.02em;
                white-space: nowrap;
            }

            body.ai-module-page .ai-badge-success {
                background: var(--ai-badge-success-bg);
                color: var(--ai-success);
            }
            body.ai-module-page .ai-badge-warning {
                background: var(--ai-badge-warning-bg);
                color: var(--ai-warning);
            }
            body.ai-module-page .ai-badge-danger {
                background: var(--ai-badge-danger-bg);
                color: var(--ai-danger);
            }
            body.ai-module-page .ai-badge-info {
                background: var(--ai-badge-info-bg);
                color: var(--ai-accent);
            }
            body.ai-module-page .ai-badge-purple {
                background: var(--ai-badge-purple-bg);
                color: var(--ai-accent-2);
            }

            /* ================================================================
               10. Form Styling
               ================================================================ */
            body.ai-module-page .ai-form-group {
                margin-bottom: 1.1rem;
            }

            body.ai-module-page .ai-form-label {
                display: block;
                font-size: 0.8rem;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: var(--ai-text-muted);
                margin-bottom: 0.35rem;
            }

            body.ai-module-page .ai-form-input,
            body.ai-module-page .ai-form-select,
            body.ai-module-page .ai-form-textarea {
                width: 100%;
                padding: 0.6rem 0.9rem;
                font-size: 0.9rem;
                line-height: 1.5;
                color: var(--ai-text-primary);
                background: var(--ai-input-bg);
                border: 1px solid var(--ai-input-border);
                border-radius: 0.6rem;
                outline: none;
                transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
            }

            body.ai-module-page .ai-form-input:focus,
            body.ai-module-page .ai-form-select:focus,
            body.ai-module-page .ai-form-textarea:focus {
                border-color: var(--ai-input-focus-border);
                box-shadow: 0 0 0 3px var(--ai-accent-glow);
                background: var(--ai-card-bg);
            }

            body.ai-module-page .ai-form-input::placeholder,
            body.ai-module-page .ai-form-textarea::placeholder {
                color: var(--ai-text-muted);
                opacity: 0.7;
            }

            body.ai-module-page .ai-form-select {
                appearance: none;
                -webkit-appearance: none;
                background-image: url("data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="%2394a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"%3E%3Cpolyline points="6 9 12 15 18 9"%3E%3C/polyline%3E%3C/svg%3E");
                background-repeat: no-repeat;
                background-position: right 0.8rem center;
                padding-right: 2.2rem;
                cursor: pointer;
            }

            body.ai-module-page .ai-form-textarea {
                min-height: 7rem;
                resize: vertical;
            }
            /* ================================================================
               11. Responsive Grid Layouts
               ================================================================ */
            body.ai-module-page .ai-grid {
                display: grid;
                gap: 1.25rem;
            }

            body.ai-module-page .ai-grid-cols-1 { grid-template-columns: 1fr; }
            body.ai-module-page .ai-grid-cols-2 { grid-template-columns: repeat(2, 1fr); }
            body.ai-module-page .ai-grid-cols-3 { grid-template-columns: repeat(3, 1fr); }
            body.ai-module-page .ai-grid-cols-4 { grid-template-columns: repeat(4, 1fr); }

            @media (max-width: 1200px) {
                body.ai-module-page .ai-grid-cols-4 { grid-template-columns: repeat(3, 1fr); }
                body.ai-module-page .ai-grid-cols-3 { grid-template-columns: repeat(2, 1fr); }
            }

            @media (max-width: 900px) {
                body.ai-module-page .ai-grid-cols-4,
                body.ai-module-page .ai-grid-cols-3 { grid-template-columns: repeat(2, 1fr); }
                body.ai-module-page .ai-grid-cols-2 { grid-template-columns: 1fr; }
                body.ai-module-page .ai-page-container {
                    padding: 1rem 1rem 2rem;
                }
                body.ai-module-page .ai-page-header {
                    padding: 1.75rem 1.5rem 1.5rem;
                }
                body.ai-module-page .ai-page-title {
                    font-size: 1.5rem;
                }
            }

            @media (max-width: 640px) {
                body.ai-module-page .ai-page-container {
                    padding: 0.75rem 0.75rem 1.5rem;
                }
                body.ai-module-page .ai-page-header {
                    padding: 1.25rem 1rem 1.25rem;
                    border-radius: 0.875rem;
                }
                body.ai-module-page .ai-page-title {
                    font-size: 1.25rem;
                }
                body.ai-module-page .ai-content-card {
                    padding: 1rem;
                    border-radius: 0.75rem;
                }
                body.ai-module-page .ai-tabs {
                    border-radius: 0.6rem;
                }
                body.ai-module-page .ai-tab {
                    font-size: 0.8rem;
                    padding: 0.45rem 0.65rem;
                }
            }

            /* ================================================================
               12. Utility Animations & Misc
               ================================================================ */
            body.ai-module-page .ai-fade-in {
                animation: aiFadeIn 0.35s ease forwards;
            }

            @keyframes aiFadeIn {
                from { opacity: 0; transform: translateY(6px); }
                to   { opacity: 1; transform: translateY(0); }
            }

            body.ai-module-page .ai-stagger-1 { animation-delay: 0.04s; }
            body.ai-module-page .ai-stagger-2 { animation-delay: 0.08s; }
            body.ai-module-page .ai-stagger-3 { animation-delay: 0.12s; }
            body.ai-module-page .ai-stagger-4 { animation-delay: 0.16s; }
            body.ai-module-page .ai-stagger-5 { animation-delay: 0.20s; }

            /* Smooth scroll */
            html { scroll-behavior: smooth; }

            /* Scrollbar */
            body.ai-module-page ::-webkit-scrollbar {
                width: 6px;
                height: 6px;
            }
            body.ai-module-page ::-webkit-scrollbar-track {
                background: transparent;
            }
            body.ai-module-page ::-webkit-scrollbar-thumb {
                background: rgba(148, 163, 184, 0.25);
                border-radius: 9999px;
            }
            body.ai-module-page ::-webkit-scrollbar-thumb:hover {
                background: rgba(148, 163, 184, 0.45);
            }
        </style>
        <?php
    }
}

if (!function_exists('ai_render_page_header')) {
    function ai_render_page_header(string $title, string $subtitle = "", string $icon = "fas fa-robot"): void
    {
        $safeTitle   = htmlspecialchars($title, ENT_QUOTES, "UTF-8");
        $safeSub     = $subtitle ? "<p class=\"ai-page-subtitle\">" . htmlspecialchars($subtitle, ENT_QUOTES, "UTF-8") . "</p>" : "";
        $safeIcon    = htmlspecialchars($icon, ENT_QUOTES, "UTF-8");
        ?>
        <div class="ai-page-header">
            <h1 class="ai-page-title">
                <span class="ai-page-icon"><i class="<?= $safeIcon ?>"></i></span>
                <?= $safeTitle ?>
            </h1>
            <?= $safeSub ?>
        </div>
        <?php
    }
}


if (!function_exists('ai_render_module_nav')) {
    function ai_render_module_nav(string $activeId = 'ai.hub'): void
    {
        $db = function_exists('getDB') ? getDB() : null;
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $destinations = ai_get_accessible_ai_destinations($db, $userId);

        $navOrder = [
            'ai.hub' => ['label' => 'Hub', 'icon' => 'fas fa-grid-2'],
            'ai.assistant' => ['label' => 'Assistant', 'icon' => 'fas fa-robot'],
            'ai.copilot' => ['label' => 'Copilot', 'icon' => 'fas fa-robot'],
            'ai.command_center' => ['label' => 'Command Center', 'icon' => 'fas fa-terminal'],
            'ai.tasks' => ['label' => 'Tasks', 'icon' => 'fas fa-list-check'],
            'ai.management' => ['label' => 'Governance', 'icon' => 'fas fa-shield-halved'],
            'ai.workflows' => ['label' => 'Workflows', 'icon' => 'fas fa-diagram-project'],
            'ai.automation' => ['label' => 'Automation', 'icon' => 'fas fa-bolt'],
            'ai.automations' => ['label' => 'Automations', 'icon' => 'fas fa-bolt'],
            'ai.knowledge' => ['label' => 'Knowledge', 'icon' => 'fas fa-book-open'],
            'ai.documents' => ['label' => 'Documents', 'icon' => 'fas fa-file-alt'],
            'ai.communications' => ['label' => 'Comms', 'icon' => 'fas fa-comments'],
            'ai.workspaces' => ['label' => 'Workspaces', 'icon' => 'fas fa-users'],
            'ai.skills' => ['label' => 'Skills', 'icon' => 'fas fa-star'],
            'ai.insights' => ['label' => 'Insights', 'icon' => 'fas fa-lightbulb'],
            'ai.analytics' => ['label' => 'Analytics', 'icon' => 'fas fa-chart-pie'],
            'ai.integrations' => ['label' => 'Integrations', 'icon' => 'fas fa-plug'],
            'ai.observability' => ['label' => 'Observability', 'icon' => 'fas fa-wave-square'],
            'ai.monitoring' => ['label' => 'Monitoring', 'icon' => 'fas fa-chart-line'],
            'ai.providers' => ['label' => 'Providers', 'icon' => 'fas fa-server'],
            'ai.models' => ['label' => 'Models', 'icon' => 'fas fa-cube'],
            'ai.intelligence' => ['label' => 'Intelligence', 'icon' => 'fas fa-radar'],
            'ai.process_intelligence' => ['label' => 'Process', 'icon' => 'fas fa-diagram-project'],
            'ai.voice' => ['label' => 'Voice', 'icon' => 'fas fa-microphone'],
            'ai.agents' => ['label' => 'Agents', 'icon' => 'fas fa-user-astronaut'],
            'ai.tools' => ['label' => 'Tools', 'icon' => 'fas fa-wrench'],
            'ai.settings' => ['label' => 'Settings', 'icon' => 'fas fa-sliders-h'],
        ];

        $lookup = [];
        foreach ($destinations as $dest) {
            $lookup[$dest['id']] = $dest;
        }
        ?>
        <div class="ai-shell-nav" aria-label="AI Module Navigation">
            <?php foreach ($navOrder as $id => $meta): ?>
                <?php if ($id === 'ai.hub'): ?>
                    <a href="<?= url('modules/ai/index.php') ?>" class="ai-shell-nav-link <?= $activeId === $id ? 'active' : '' ?>">
                        <i class="<?= htmlspecialchars($meta['icon']) ?>"></i>
                        <span><?= htmlspecialchars($meta['label']) ?></span>
                    </a>
                    <?php continue; ?>
                <?php endif; ?>
                <?php if (empty($lookup[$id])) continue; ?>
                <a href="<?= url($lookup[$id]['url']) ?>" class="ai-shell-nav-link <?= $activeId === $id ? 'active' : '' ?>">
                    <i class="<?= htmlspecialchars($meta['icon']) ?>"></i>
                    <span><?= htmlspecialchars($meta['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php
    }
}

if (!function_exists('ai_render_hub_cards')) {
    function ai_render_hub_cards(array $destinations): void
    {
        $groups = ai_group_destinations_for_hub($destinations);
        ?>
        <div class="space-y-6">
            <?php foreach ($groups as $group): ?>
                <section class="space-y-3">
                    <div class="flex items-center gap-3">
                        <div class="w-1.5 h-7 rounded-full" style="background: linear-gradient(180deg, #38bdf8, #8b5cf6)"></div>
                        <div>
                            <h2 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500"><?= htmlspecialchars($group['label']) ?></h2>
                            <p class="text-sm text-slate-400">Curated entry points for <?= htmlspecialchars(strtolower($group['label'])) ?>.</p>
                        </div>
                    </div>
                    <div class="ai-shell-grid">
                        <?php foreach ($group['items'] as $dest): ?>
                            <?php
                            $sectionTag = trim(($dest['section'] ?? '') !== '' ? $dest['section'] : $dest['module']);
                            $description = $dest['description'] ?? 'Open this AI workspace.';
                            ?>
                            <a class="ai-shell-card" href="<?= url($dest['url']) ?>">
                                <div class="ai-shell-card-head">
                                    <div>
                                        <div class="ai-shell-chip"><?= htmlspecialchars($sectionTag) ?></div>
                                        <div class="ai-shell-card-title mt-3"><?= htmlspecialchars($dest['name']) ?></div>
                                    </div>
                                    <div class="ai-shell-card-icon">
                                        <i class="<?= htmlspecialchars($dest['icon'] ?? 'fas fa-arrow-right') ?>"></i>
                                    </div>
                                </div>
                                <div class="ai-shell-card-desc"><?= htmlspecialchars($description) ?></div>
                                <div class="flex items-center justify-between mt-auto text-xs text-slate-300">
                                    <span><?= htmlspecialchars(str_replace('_', ' ', $dest['module'])) ?></span>
                                    <span><i class="fas fa-arrow-right-long"></i></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
        <?php
    }
}

if (!function_exists('ai_render_chat_launcher')) {
    function ai_render_chat_launcher(?string $assistantUrl = null, string $label = 'Open AI Chat'): void
    {
        $assistantUrl = $assistantUrl ?: url('modules/ai/assistant/index.php?embed=1');
        ai_render_shell_styles();
        ?>
        <div class="ai-shell-launcher">
            <div class="flex items-end">
                <div class="ai-shell-launcher-label">
                    <i class="fas fa-comment-dots mr-2 text-sky-400"></i>
                    <span><?= htmlspecialchars($label) ?></span>
                </div>
                <button type="button" class="ai-shell-launcher-button" onclick="openAiShellChat()"
                        aria-label="Open AI chat">
                    <i class="fas fa-comment-dots text-lg"></i>
                </button>
            </div>
        </div>

        <div class="ai-shell-modal" id="aiShellModal" aria-hidden="true">
            <div class="ai-shell-panel" role="dialog" aria-modal="true" aria-label="AI Chat Window">
                <div class="ai-shell-panel-bar">
                    <div class="flex items-center gap-3">
                        <div class="ai-shell-card-icon" style="width: 2.5rem; height: 2.5rem; background: rgba(56, 189, 248, 0.14);">
                            <i class="fas fa-robot"></i>
                        </div>
                        <div>
                            <div class="font-semibold text-sm text-white">AI Assistant</div>
                            <div class="text-xs text-slate-300">Popup conversation window</div>
                        </div>
                    </div>
                    <button type="button" class="ai-shell-panel-close" onclick="closeAiShellChat()" aria-label="Close chat">
                        <i class="fas fa-xmark"></i>
                    </button>
                </div>
                <iframe class="ai-shell-panel-frame" id="aiShellFrame" data-src="<?= htmlspecialchars($assistantUrl) ?>" src="about:blank" title="AI Assistant"></iframe>
            </div>
        </div>

        <script>
            function openAiShellChat() {
                const modal = document.getElementById('aiShellModal');
                const frame = document.getElementById('aiShellFrame');
                if (!modal) return;
                if (frame && !frame.dataset.loaded) {
                    frame.src = frame.dataset.src || frame.src;
                    frame.dataset.loaded = '1';
                }
                modal.classList.add('open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }

            function closeAiShellChat() {
                const modal = document.getElementById('aiShellModal');
                if (!modal) return;
                modal.classList.remove('open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeAiShellChat();
                }
            });

            document.addEventListener('click', function (event) {
                const modal = document.getElementById('aiShellModal');
                if (!modal || !modal.classList.contains('open')) return;
                if (event.target === modal) {
                    closeAiShellChat();
                }
            });
        </script>
        <?php
    }
}
