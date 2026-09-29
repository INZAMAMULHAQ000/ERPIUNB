<?php

/**
 * Sidebar Menu Helper
 *
 * Loads sidebar menu items from the database so the sidebar
 * can be extended without editing templates.
 */

/**
 * Returns all active sidebar menu items grouped by section.
 * Results are static-cached per request.
 */
function getSidebarMenu(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $db = getDB();
    $stmt = $db->query("
        SELECT id, parent_id, section, section_order, label, icon,
               route, permission, sort_order, is_active, is_indented
        FROM sidebar_menu
        WHERE is_active = 1
        ORDER BY section_order ASC, sort_order ASC, id ASC
    ");
    $rows = $stmt->fetchAll();

    // Build parent-child map for items that reference parent_id
    $byParentId = [];
    $orphans = [];
    foreach ($rows as $r) {
        if ($r['parent_id']) {
            $byParentId[$r['parent_id']][] = $r;
        } else {
            $orphans[] = $r; // will be re-grouped below
        }
    }

    /* If the original code used explicit id=100..105 (the sidebar schema
       writes Finance items with explicit ids and Categories references
       expenses via is_indented rather than parent_id). For now we only
       use the flat grouping — parent_id support is ready for future use. */

    // Group by section
    $groups = [];
    foreach ($orphans as $r) {
        $section = $r['section'] ?? '__top__';
        $order = $r['section_order'] ?? 999;
        if (!isset($groups[$section])) {
            $groups[$section] = [
                'section'      => $r['section'],
                'section_order' => $order,
                'items'        => [],
            ];
        }
        $groups[$section]['items'][] = $r;
    }

    uasort($groups, fn($a, $b) => $a['section_order'] <=> $b['section_order']);

    // Attach children to their parents
    if (!empty($byParentId)) {
        foreach ($groups as &$g) {
            foreach ($g['items'] as &$item) {
                $pid = $item['id'];
                if (isset($byParentId[$pid])) {
                    $item['children'] = $byParentId[$pid];
                }
            }
        }
        unset($g, $item);
    }

    $cache = $groups;
    return $cache;
}

/**
 * Render the full sidebar HTML.
 * Call this from sidebar.php instead of hardcoded markup.
 */
function renderSidebar(): void
{
    $groups = getSidebarMenu();
    $first = true;
    ?>
    <aside id="sidebar" class="fixed left-0 top-16 bottom-0 overflow-y-auto hidden md:block shadow-lg" style="width: var(--sidebar-width, 4rem);">
        <div id="sidebar-resizer" class="sidebar-resizer"></div>
        <div class="py-4">
    <?php foreach ($groups as $group):
        $sectionLabel = $group['section'];
        $items = $group['items'];

        // Filter items by permission
        $visible = array_filter($items, function ($item) {
            $perm = $item['permission'] ?? null;
            return $perm === null || $perm === '' || hasPermission($perm);
        });

        if (empty($visible)) {
            continue;
        }

        // Separator before each group (except the first if it has no section label)
        if (!$first) : ?>
            <div class="border-t my-2"></div>
        <?php endif;
        $first = false;

        // Section title (skip for the top-level unnamed group)
        if ($sectionLabel !== null && $sectionLabel !== ''): ?>
            <div class="section-title"><?= htmlspecialchars($sectionLabel) ?></div>
        <?php endif;

        // Render items
        foreach ($items as $item):
            $perm = $item['permission'] ?? null;
            if ($perm !== null && $perm !== '' && !hasPermission($perm)) {
                continue;
            }

            $indentClass = !empty($item['is_indented']) ? ' pl-10' : '';
            $href = url($item['route']);
            $icon = htmlspecialchars($item['icon']);
            $label = htmlspecialchars($item['label']);
            ?>
            <a href="<?= $href ?>" class="flex items-center px-6 py-3 text-gray-700 hover:bg-gray-100<?= $indentClass ?>" data-label="<?= $label ?>">
                <i class="<?= $icon ?> w-6"></i>
                <span class="nav-label"><?= $label ?></span>
            </a>
        <?php endforeach; ?>
    <?php endforeach; ?>
        </div>
    </aside>
    <?php
}
