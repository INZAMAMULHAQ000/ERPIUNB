<aside id="sidebar">
    <div class="sidebar-content">
        <div class="py-4 pb-12">
            <a href="<?= url('index.php') ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>

            <!-- ===== System (Users, Roles, Permissions, People) ===== -->
            <?php if (hasPermission('users.view') || hasPermission('roles.view') || hasPermission('permissions.view') || hasPermission('people_management.view')): ?>
            <a href="<?= url('modules/system/index.php') ?>">
                <i class="fas fa-server"></i>
                <span>System</span>
            </a>
            <?php endif; ?>

            <!-- ===== Companies ===== -->
            <?php if (hasPermission('company.view')): ?>
            <a href="<?= url('modules/companies/index.php') ?>">
                <i class="fas fa-building"></i>
                <span>Companies</span>
            </a>
            <?php endif; ?>

            <!-- ===== HRMS ===== -->
            <?php if (hasPermission('departments.view') || hasPermission('designations.view') || hasPermission('employees.view') || hasPermission('attendance.view') || hasPermission('leaves.view') || hasPermission('holidays.view')): ?>
            <a href="<?= url('modules/hrms_dashboard/index.php') ?>">
                <i class="fas fa-users-cog"></i>
                <span>HRMS</span>
            </a>
            <?php endif; ?>

            <!-- ===== Projects ===== -->
            <?php if (hasPermission('projects.view') || hasPermission('tasks.view') || hasPermission('timesheets.view')): ?>
            <a href="<?= url('modules/projects_dashboard/index.php') ?>">
                <i class="fas fa-project-diagram"></i>
                <span>Projects</span>
            </a>
            <?php endif; ?>

            <!-- ===== CRM ===== -->
            <?php if (hasPermission('leads.view') || hasPermission('clients.view') || hasPermission('deals.view')): ?>
            <a href="<?= url('modules/crm_dashboard/index.php') ?>">
                <i class="fas fa-chart-line"></i>
                <span>CRM</span>
            </a>
            <?php endif; ?>

            <!-- ===== Customers ===== -->
            <?php if (hasPermission('customers.view')): ?>
            <a href="<?= url('modules/customers/index.php') ?>">
                <i class="fas fa-people-group"></i>
                <span>Customers</span>
            </a>
            <?php endif; ?>

            <!-- ===== Finance ===== -->
            <?php if (hasPermission('finance.view') || hasPermission('budget.view') || hasPermission('budget.transfer') || hasPermission('budget.revise') || hasPermission('budget.report')): ?>
            <a href="<?= url('modules/finance/index.php') ?>">
                <i class="fas fa-coins"></i>
                <span>Finance</span>
            </a>
            <?php endif; ?>

            <!-- ===== Assets ===== -->
            <?php if (hasPermission('assets.view') || hasPermission('asset_assignments.view') || hasPermission('maintenance.view') || hasPermission('licenses.view') || hasPermission('vendors.view') || hasPermission('inventory.view')): ?>
            <a href="<?= url('modules/assets_dashboard/index.php') ?>">
                <i class="fas fa-desktop"></i>
                <span>Assets</span>
            </a>
            <?php endif; ?>

            <!-- ===== Inventory ===== -->
            <?php if (hasPermission('inventory.view') || hasPermission('inventory.adjust') || hasPermission('inventory.transfer') || hasPermission('inventory.reports') || hasPermission('inventory.settings')): ?>
            <a href="<?= url('modules/inventory/index.php') ?>">
                <i class="fas fa-boxes"></i>
                <span>Inventory</span>
            </a>
            <?php endif; ?>

            <!-- ===== Procurement ===== -->
            <?php if (hasPermission('procurement.view') || hasPermission('procurement.rfq.view')): ?>
            <a href="<?= url('modules/procurement/dashboard/index.php') ?>">
                <i class="fas fa-cart-shopping"></i>
                <span>Procurement</span>
            </a>
            <?php endif; ?>

            <!-- ===== Helpdesk ===== -->
            <?php if (hasPermission('helpdesk_dashboard.view') || hasPermission('tickets.view') || hasPermission('ticket_categories.view') || hasPermission('slas.view') || hasPermission('knowledgebase.view')): ?>
            <a href="<?= url('modules/helpdesk_dashboard/index.php') ?>">
                <i class="fas fa-headset"></i>
                <span>Helpdesk</span>
            </a>
            <?php endif; ?>

            <!-- ===== Reports & Analytics ===== -->
            <?php if (hasPermission('analytics.view') || hasPermission('reports.view') || hasPermission('scheduled_reports.view') || hasPermission('auditlogs.view')): ?>
            <a href="<?= url('modules/executive_dashboard/index.php') ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Reports &amp; Analytics</span>
            </a>
            <?php endif; ?>

            <!-- ===== AI ===== -->
            <?php if (hasPermission('ai.assistant.access') || hasPermission('ai.providers.view') || hasPermission('ai.models.view') || hasPermission('ai.agents.view') || hasPermission('ai.knowledge.view') || hasPermission('ai.tools.view')): ?>
            <a href="<?= url('modules/ai/index.php') ?>">
                <i class="fas fa-robot"></i>
                <span>AI</span>
            </a>
            <?php endif; ?>

            <!-- ===== Settings ===== -->
            <div style="padding: 0.75rem 1.5rem 0.35rem; font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #475569;">
                Settings
            </div>
            <?php if (hasPermission('settings.view')): ?>
            <a href="<?= url('modules/settings/index.php') ?>">
                <i class="fas fa-sliders"></i>
                <span>Configuration</span>
            </a>
            <?php endif; ?>
            <a href="<?= url('modules/appearance/index.php') ?>">
                <i class="fas fa-palette"></i>
                <span>Appearance</span>
            </a>
            <?php if (hasPermission('widgets.manage')): ?>
            <a href="<?= url('modules/widgets/index.php') ?>">
                <i class="fas fa-puzzle-piece"></i>
                <span>Widgets</span>
            </a>
            <?php endif; ?>
            <?php if (hasPermission('template_designer.view')): ?>
            <a href="<?= url('modules/template_designer/index.php') ?>">
                <i class="fas fa-pen-ruler"></i>
                <span>Template Designer</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
</aside>
