<?php
/**
 * AI Module Hub.
 */
$pageTitle = 'AI Hub';
require_once __DIR__ . '/bootstrap.php';
require_once AI_MODULE_DIR . '/includes/ai_ui.php';

$db = getDB();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$destinations = ai_get_accessible_ai_destinations($db, $userId);

if (empty($destinations) && ($_SESSION['role'] ?? '') !== 'superadmin') {
    die('Access Denied');
}

require_once AI_APP_ROOT . '/includes/header.php';
ai_render_page_styles();
?>

<div class="ai-page-container space-y-6">
    <section class="ai-shell-banner ai-page-header px-6 py-6 md:px-8 md:py-8">
        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6">
            <div class="max-w-3xl">
                <div class="inline-flex items-center gap-2 rounded-full border border-sky-400/30 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-sky-200">
                    <i class="fas fa-brain"></i> AI Module Hub
                </div>
                <h1 class="ai-shell-title mt-4 text-3xl md:text-4xl font-bold">
                    AI workspace, organized as a launchpad instead of scattered pages.
                </h1>
                <p class="ai-shell-subtitle mt-3 text-sm md:text-base max-w-2xl">
                    Open assistant, governance, workflows, knowledge, monitoring, providers, and models from one structured screen.
                </p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="<?= url('modules/ai/assistant/index.php') ?>" class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-slate-900 shadow-lg shadow-sky-500/20">
                    <i class="fas fa-comment-dots text-sky-600"></i> Open Assistant
                </a>
                <a href="<?= url('modules/ai/management/index.php') ?>" class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/5 px-4 py-2 text-sm font-semibold text-white hover:bg-white/10">
                    <i class="fas fa-shield-halved text-teal-300"></i> Governance
                </a>
            </div>
        </div>
    </section>

    <section class="ai-section space-y-3">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Quick Navigation</h2>
                <p class="text-sm text-slate-500">Cards below open the major AI workspaces.</p>
            </div>
            <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600">
                <i class="fas fa-circle text-emerald-500 text-[0.55rem]"></i> <?= count($destinations) ?> accessible pages
            </span>
        </div>
        <?php ai_render_module_nav('ai.hub'); ?>
    </section>

    <?php ai_render_hub_cards($destinations); ?>
</div>

<?php require_once AI_APP_ROOT . '/includes/footer.php'; ?>
