# AGENTS.md

## Project Overview

**Enterprise ERP System** — A modular Enterprise Resource Planning system built with vanilla PHP 8.1+ and MySQL. Zero framework dependencies, 52 feature modules.

**Purpose:** Comprehensive business management system covering HR, payroll posting, finance, procurement, CRM, projects, helpdesk, and AI-powered automation.

**Tech Stack:**
- PHP 8.1+ (vanilla, zero framework dependencies)
- MySQL/MariaDB with PDO prepared statements
- Tailwind CSS (CDN-based, no build step)
- Chart.js 4 + Carbon Charts for analytics
- Apache mod_rewrite for routing

> **Read this before trusting the rest of the file.** This document was previously inaccurate in ways that wasted engineering time: it described `config/schema.sql`, numbered migrations `001–047`, `config/runner.php`, `FixedAssetService`, and a set of `test_*_suite.php` files that **did not exist**. The authoritative schema is now `database/schema.sql` (generated from the live database). Commands and files listed below have been verified to exist.

## Directory Structure

```
erp-root/
├── config/                # Database, schemas, migrations, seeds
├── database/              # Database backup files
├── helpers/               # Shared utilities (security, RBAC, formatting)
├── includes/              # Core layout (header, footer)
├── middleware/            # Authentication guard
├── modules/               # 60+ feature modules (self-contained CRUD)
├── templates/             # Reusable UI components
├── assets/                # CSS, JS, uploads
├── uploads/               # User-uploaded documents
├── api/                   # API endpoints
├── widgets/               # Dashboard widgets
├── .agents/               # Agent skills
├── .clancel/              # Claude skills
├── .crush/                # Crush configuration
├── .cursor/               # Cursor rules
└── *.php / *.sql / *.md    # Entry points, migrations, documentation
```

## Core Architecture

### Request Flow
1. **Entry Point:** `index.php` (dashboard) or module routes via `.htaccess` rewrite
2. **Authentication:** `middleware/auth.php` requires login via `includes/header.php`
3. **RBAC Check:** `helpers/rbac.php` validates permissions before allowing access
4. **Module Execution:** Module's `index.php` or action file executes
5. **Template Rendering:** `includes/header.php` → Module content → `includes/footer.php`

`includes/header.php` is the whole framework: it opens the output buffer, starts the session, requires every helper, calls `requireAuth()`, handles `?set_currency` / `?set_theme` / `?set_company`, resolves the active company, then renders navbar + sidebar. Any page that requires it is authenticated. `ob_end_flush()` lives in `includes/footer.php`, so **every page must include the footer** or the layout never closes.

### Master data and multi-company

| Module | Physical table | Notes |
|---|---|---|
| `modules/customers/` | **`clients`** | Customer master. Table name is legacy and deliberate — 6 tables FK to it. Do not create a `customers` table. |
| `modules/companies/` | **`companies`** | Company master. 13 tables FK to it, 130+ carry `company_id`. |
| `modules/settings/` | `app_settings`, `currencies`, `currency_rates`, `document_sequences`, `lookup_*` | Configuration console — the values that used to be hardcoded. |
| `modules/system/` | `users`, `roles`, `permissions`, `people` | Landing dashboard grouping Users / Roles / Permissions / People. |

- **Active company** comes from `$_SESSION['active_company_id']`, resolved by `activeCompany()` in `helpers/company.php`. Access rule matches `CompanyService`: role id 1 (Super Admin) sees every company, everyone else only their `user_companies` rows.
- **Postings are company-explicit.** `PostingEngine` refuses to post when a journal has no company or no financial year for its own company — there is no `?? 1` fallback, and it will not borrow another company's year.
- **Document numbers** come from `document_sequences` via `nextDocumentNumber()` (atomic read-and-increment). Use `peekDocumentNumber()` for form previews so viewing a page never consumes a number. Procurement and journal vouchers keep their own atomic sequences (`procurement_sequences`, `voucher_types.voucher_prefix`).
- `sidebar_menu` is still written by `modules/menu_manager/` but **nothing renders from it** — navigation is hardcoded in `templates/sidebar.php`.

### Routing System
- **Clean URLs:** `.htaccess` rewrites `/modules/users/index` to `/modules/users/index.php`
- **Auto-Append:** Missing extensions automatically added (e.g., `users` → `users.php`)
- **File Access Denied:** Config, helpers, templates, `.sql`, `.md`, `.env`, `_debug` blocked

### Security Layer
- **Session Management:** `requiresAuth()` in `middleware/auth.php`
- **RBAC:** `hasPermission($permission)` checks format: `module.action` or `module.submodule.action`
- **XSS Prevention:** `sanitize()` function (htmlspecialchars with ENT_QUOTES)
- **CSRF:** Token-based protection (in security helpers)
- **SQL Injection:** PDO prepared statements throughout
- **Directory Protection:** `.htaccess` blocks sensitive directories

## Essential Commands

All commands below are run from the repository root and have been verified to exist.

### Setup & migrations
```bash
# Rebuild an empty database from the committed snapshot, then apply migrations
mysql -u root erp_db < database/schema.sql

# Idempotent migrations (safe to re-run; each checks before altering)
php config/migrations/2026_10_12_customer_master.php
php config/migrations/2026_10_12_company_master.php
php config/migrations/2026_10_12_company_scoped_posting.php
php config/migrations/2026_10_12_configuration_layer.php
php config/migrations/2026_10_12_sequence_formats.php

php setup.php                    # dashboard widget system (CLI only)
php config/seed_data.php         # seed sample data
```

There is **no migration runner and no migration-tracking table**. `config/migrations/runner.php` only exposes an `applySql()` helper; migrations are applied by invoking each file directly. Migrations are written to be idempotent (they query `information_schema` before altering), which is what makes re-running safe.

### Regenerate the schema snapshot
```bash
mysqldump -u root --no-data --skip-comments --routines --triggers erp_db > database/schema.sql
```
`database/schema.sql` is deliberately the one `.sql` file that is version-controlled (`.gitignore` has a `!` exception for it); every other `.sql` stays ignored so dumps and backups never get committed.

### Schedulers & maintenance (CLI)
```bash
php ai_automation_scheduler.php   # cron: AI automations
php erp_module_health_check.php   # module health report
php enable_workflow_studio.php    # workflow studio toggle
php maintenance.php clean users   # guarded maintenance console
```
These are blocked over HTTP by `.htaccess` and must be run from the command line.

### Testing
There is **no automated test suite in this repository.** The `test_*.php` and `check_*.php` files previously listed here were never committed (`.gitignore` excludes `test*.php` and `check_*.php`). Verification is done by:
```bash
# 1. Every PHP file must parse
find . -name "*.php" -not -path "./.git/*" -print0 \
  | xargs -0 -I{} sh -c 'php -l "{}" >/dev/null 2>&1 || echo "BROKEN {}"'

# 2. Load every page and assert no PHP errors surface
#    (see the manual test checklist in CLEANUP_PLAN.md / project notes)
```

## Code Organization

### Module Structure
Each module in `modules/` is self-contained with:
- `index.php` — List view (table/grid)
- `create.php` — Create form
- `edit.php` — Edit form
- `view.php` — Detail view
- `delete.php` — Delete action
- Subdirectories for complex modules (e.g., `finance/` has 100+ files)

**Key Modules:**

**Finance Module** (`modules/finance/`)
- `chart_of_accounts/` — COA management
- `journal_entries/` — Double-entry bookkeeping
- `general_ledger/` — Financial statements
- `budgets/` — Budgeting system
- `payroll/` — Payroll processing
- `period_close/` — Period closing (F12) & year-end closing (F13)
- `intercompany/` — Multicompany consolidation
- `reports/` — 20+ financial reports (P&L, balance sheet, cash flow, etc.)
- `fixed_assets/` — Fixed asset lifecycle management (F9)
- `tax/` — Tax management (VAT, GST, withholding)

**AI Module** (`modules/ai/`)
- `services/` — 80+ AI services (AIAgentService, AIDocumentIntelligenceService, etc.)
- `workflows/` — AI workflow orchestration
- `knowledge/` — Knowledge base with vector search (RAG)
- `integrations/` — External system integrations
- `governance/` — AI safety, policy enforcement, observability
- `copilot/` — AI copilot interface
- `agents/` — AI agents for tasks
- `providers/` — AI model providers (OpenAI, Anthropic, etc.)
- `analytics/` — AI-powered analytics

**HR Module** (`modules/attendance/`, `modules/payroll/`, `modules/leave_requests/`)
- Attendance tracking with check-in/check-out
- Leave request workflow with approval
- Payroll processing with tax, deductions, benefits
- Time tracking per project

**CRM Module** (`modules/leads/`, `modules/clients/`, `modules/deals/`)
- Lead capture and qualification
- Client management with contacts
- Deal pipeline tracking

**Procurement Module** (`modules/procurement/`)
- `rfq/` — Request for Quotation
- `purchase_orders/` — Purchase orders
- `requisitions/` — Purchase requisitions
- `quotations/` — Quotation comparison

**Project Management Module** (`modules/projects/`, `modules/tasks/`)
- Project lifecycle management
- Kanban-style task boards
- Milestones and assignments
- Timesheets

**Helpdesk Module** (`modules/tickets/`, `modules/knowledge_base/`)
- Issue tracking with SLA
- Knowledge base articles

**Reports Module** (`modules/reports/`)
- Custom report builder
- Scheduled reports with exports
- Print/CSV/PDF export

### Helpers
- `security.php` — `sanitize()`, `detectBaseUrl()` (defines `BASE_URL`), `csrfToken()`, `verifyCsrf()`, `logAudit()`, `getClientIP()`, flash helpers
- `rbac.php` — `hasPermission()`, `hasRole()`; permission normalisation and a hand-maintained module prefix map
- `url.php` — `url()`, `resolveAppUrl()`, `normalizeAppUrl()` (base-URL detection itself lives in `security.php`)
- `validation.php` — `validate()`, `old()`, `saveFormState()`, `restoreFormErrors()`, `clearOldInput()`
- `pagination.php` — `paginate()`, `getPageNumber()`, `paginationLinks()`
- `breadcrumbs.php` — filesystem-driven breadcrumbs, `renderBreadcrumbs()`
- `currency.php` — currencies and FX rates resolved from the `currencies` / `currency_rates` tables (no hardcoded list), `getActiveCurrency()`, `convertAmount()`, `formatMoney()`
- `company.php` — **active company context**: `activeCompany()`, `activeCompanyId()`, `appSetting()`, `appName()`, `companyFilterSql()`, `nextDocumentNumber()`, `peekDocumentNumber()`, `getCompanyFinancialYear()`
- `lookup.php` — `lookupOptions()`, `lookupLabel()`, `lookupExtra()`, `paymentTermDays()` backed by `lookup_types` / `lookup_values`
- `redirect.php` — `redirect()`, `back()`, `to()`, `redirect_to()`
- `finance_helpers.php` — `getActiveFinancialYear($db, $companyId)`, `getFinancialYearForDate()`, `generateVoucherNo()`
- `menu.php` — **removed**: it read a `sidebar_menu` table that nothing rendered
- `template_renderer.php`, `report_engine.php`, `placeholder_registry.php` — reporting and template design

### Middleware
- `auth.php` — Simple session-based authentication (`requireAuth()`)

### Templates
- `navbar.php` — top bar: brand, **company switcher**, currency switcher, theme toggle, sign out
- `sidebar.php` — the live navigation, hardcoded with one `hasPermission()` gate per link
- `footer.php` — closes the layout, sidebar JS, flushes the output buffer
- `mock_preview.php` — client preview mock, removable
- `templates/header.php` — **removed**: nothing included it; `includes/header.php` is the only shell

### Configuration
- `config/database.php` — `getDB()` PDO singleton plus the `CompatPDO` / `CompatPDOStatement` MySQLi shim; picks local vs production credentials from `SERVER_NAME`/`HTTP_HOST`
- `config/widgets.php` — widget registry and per-user dashboard layout
- `config/seed_data.php` — sample data
- `config/migrations/` — date-named idempotent PHP migrations (see Essential Commands); `runner.php` provides `applySql()` only
- `database/schema.sql` — **authoritative schema snapshot**, version-controlled
- There is **no** `config/schema.sql`, no `schema_ai_*.sql`, and no numbered `001–047` migration set.

### Database Schema Structure
- `users` — User accounts with roles, permissions
- `employees` — Employee records
- `departments` — Organizational units
- `designations` — Job titles
- `projects` — Project management
- `tasks` — Task tracking
- `tickets` — Helpdesk tickets
- `invoices` — Billing
- `payments` — Payment tracking
- `expenses` — Expense management
- `assets` — Fixed assets with lifecycle
- `chart_of_accounts` — Accounting structure
- `journal_entries` — Double-entry bookkeeping
- `general_ledger` — Financial reporting
- `financial_years` — Fiscal year management
- `period_close` — Period/year-end closing
- `ai_conversations`, `ai_knowledge`, `ai_agents`, `ai_automations`, `ai_models`, `ai_tasks`, `ai_permissions` — AI module tables

## Coding Conventions & Patterns

### File Structure
- Use `index.php` for list views
- Use `create.php`, `edit.php`, `view.php`, `delete.php` for CRUD
- Complex modules use subdirectories with similar structure

### Database Access Pattern
```php
$db = getDB(); // Returns PDO instance
$stmt = $db->prepare("SELECT * FROM table WHERE id = ?");
$stmt->execute([$id]);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

### Permission Format
- Format: `module.action` (e.g., `users.create`, `finance.payroll.process`)
- Module prefixes normalized: `fixedasset.*` → `fixed_assets.fixedasset.*`

### Theme Support
- Themes: `ibm-light`, `ibm-dark` (IBM Carbon Design System)
- Set via URL: `?set_theme=ibm-dark` or persisted in `users.theme`
- Applied to `<html>` tag and CSS classes

### Multi-Currency
- Session-based: `setActiveCurrency($currencyCode)`
- Helpers auto-convert display amounts
- Currency switcher in header

### Flash Messages
```php
$_SESSION['flash'][] = [
    'type' => 'success' | 'error' | 'info' | 'warning',
    'message' => 'Message text'
];
```
- Automatically rendered in header
- Cleared after display

## AI Module Architecture

### Service Layer (80+ services)
Located in `modules/ai/services/`:
- `AIAgentService.php` — Agent management
- `AIDocumentIntelligenceService.php` — Document processing (OCR, extraction)
- `AIKnowledgeService.php` — Knowledge base with vector search
- `AIWorkflowService.php` — Workflow orchestration
- `AIPolicyEngine.php` — Policy enforcement
- `AIGovernanceService.php` — AI safety and compliance
- `AIAnalyticsService.php` — AI-powered analytics
- `AIIntegrationService.php` — External API integration
- 70+ more specialized services

### API Endpoints (`modules/ai/api/`)
- `agents.php`, `tasks.php`, `workflows.php`, `knowledge.php`
- `chat.php`, `copilot.php`, `communications.php`
- `management.php`, `governance.php`, `integrations.php`
- `analytics.php`, `process_intelligence.php`, `workspaces.php`
- `automations.php`, `inteligence.php`, `navigation.php`
- `observability.php`, `inteligence.php`

### Governance Features
- `AIGovernanceMiddleware.php` — Request validation
- `AIPolicyEngine.php` — Rule enforcement
- `AIKillSwitchService.php** — Emergency shutdown
- `AISecurityEventService.php` — Security logging
- `AIUsageService.php` — Usage tracking
- `AIMetricRegistry.php` — Metrics collection

### Knowledge Base (RAG)
- `AIKnowledgeService.php` — Vector store integration
- `AIKnowledgeQAService.php` — Q&A generation
- `AIKnowledgeReviewService.php** — Content moderation

### Providers
- `AIProviderService.php` — Multiple provider support
- `AIModelService.php` — Model management
- `ai/providers/` — Provider-specific configurations

## Testing Approach

### Test Suite Structure
Tests are standalone PHP files that:
1. Create isolated test data (TEST-FY-F12, TEST-FY-F13, etc.)
2. Run formal test procedures
3. Restore database to original state afterward

### F12 Finance Period Closing Test (`test_f12_suite.php`)
- 11 formal tests covering:
  - Monthly period closing with blocking
  - Year-end closing
  - Reopening periods
  - Account opening balances
  - Checklists
  - Journal entry validation
  - GL reconciliation
- Uses `PeriodCloseService` and `YearEndClosingService`

### Test Files Naming Convention
- `test_f12_suite.php` — Finance F12
- `test_f13_suite.php` — Group consolidation F13
- `test_f9_complete.php` — Fixed assets F9
- `test_p1_suite.php` — Procurement P1
- `test_p2_suite.php` — RFQ P2
- `test_p3_suite.php` — PO P3

### Check Scripts (for data validation)
- `check_asset_categories.php`
- `check_users.php`
- `check_kill_switches.php`
- `check_tables.php`

## Important Gotchas & Patterns

### Module Access Patterns
- Most modules require `requireAuth()` (via header)
- Custom middleware can be added for stricter control
- Permission checks happen before rendering content

### Database Connection
- `getDB()` returns PDO instance
- Uses `CompatPDOStatement` for MySQLi-style compatibility with AI services
- UTF-8MB4 charset for emoji support

### URL Detection
- `detectBaseUrl()` automatically detects local vs production URLs
- Works across localhost, subdomains, and VPS setups
- Based on SCRIPT_NAME and filesystem path

### Session Management
- Sessions start automatically in `includes/header.php`
- Theme preference persisted in `users.theme`
- Flash messages stored in `$_SESSION['flash']`

### Module Registration
- Modules don't register themselves; permissions and menu items must be added manually
- RBAC system normalizes permissions (`fixedasset.*` → `fixed_assets.fixedasset.*`)

### Financial Year Closing (F12)
- Requires checklist completion before period/year closing
- Blocks posting to closed periods
- Creates journal entries automatically via `PostingEngine`
- Generates financial statements

### Group Consolidation (F13)
- Multicompany consolidation with intercompany eliminations
- `CompanyService`, `IntercompanyService`, `ConsolidationService`
- Generates consolidated P&L, balance sheet, cash flow

### Fixed Assets (F9)
- Asset lifecycle: acquisition → depreciation → disposal
- `FixedAssetService` and `DepreciationService`
- Monthly depreciation processing
- Gain/loss on disposal tracking

### AI Module Dependencies
- AI module depends on ERP core via `bootstrap.php`
- Requires `AI_MODULE_DIR` and `AI_APP_ROOT` constants
- Centralized config in `ai/config/ai_central_config.php`

### Migration System
- Migrations run in order (001 → 047)
- `config/runner.php` executes all migrations
- Creates new tables and alters existing ones
- No rollback mechanism (designed for one-way execution)

### Error Handling
- Production mode: errors silenced via `.htaccess` and error suppression
- Development mode: full error reporting
- Custom error handling via PHP error handler

## Security Considerations

### Input Validation
- All user input validated via `helpers/validation.php`
- `sanitize()` function used for XSS prevention
- Prepared statements for all SQL queries

### File Uploads
- Uploads stored in `uploads/` directory
- No public directory access to uploaded files
- File type validation recommended

### Directory Protection
- `.htaccess` blocks access to:
  - `config/` (database credentials)
  - `helpers/` (core utilities)
  - `middleware/` (authentication)
  - `templates/` (UI components)
  - `.sql`, `.md`, `.env`, `.jsonl` files
  - `_debug` paths

### Rate Limiting
- Not built-in; should be implemented at web server level (Apache/Nginx)

### Session Security
- Session cookie flags: HttpOnly, Secure, SameSite
- Session regeneration on login/logout
- Session timeout configuration

## Deployment Considerations

### Requirements
- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.3+
- Apache with mod_rewrite enabled
- `allow_url_fopen` enabled (for remote APIs)

### Configuration
1. Set database credentials in `config/database.php`
2. Import schema via CLI or browser
3. Run migrations via `config/runner.php`
4. Seed data via `config/seed_data.php` (optional)
5. Set up cron jobs for schedulers

### Environment Detection
- Local: localhost, 127.0.0.1, ::1
- Production: All other hosts
- Production hides errors

### Performance Optimization
- Database indexes on foreign keys and frequently queried columns
- Pagination on list views (in helpers)
- AJAX for dynamic operations
- CSS/JS minification recommended for production

## Common Development Patterns

### Creating a New Module
1. Create directory in `modules/`
2. Create `index.php`, `create.php`, `edit.php`, `view.php`, `delete.php`
3. Add permissions to `permissions` table
4. Register menu items (if needed)
5. Add RBAC checks with `hasPermission()`

### Database Queries
```php
$db = getDB();
$stmt = $db->prepare("SELECT * FROM table WHERE id = ?");
$stmt->execute([$id]);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

### Form Validation
```php
$validated = validate($_POST, [
    'email' => ['required', 'email'],
    'name' => ['required', 'min:3'],
]);
```

### Pagination
```php
$page = $_GET['page'] ?? 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;
// Use pagination helper
```

### Redirect
```php
require_once __DIR__ . '/../helpers/redirect.php';
to('modules/users/index.php');
```

## External Dependencies

### CDNs
- Tailwind CSS: `https://cdn.tailwindcss.com`
- Chart.js: `https://cdn.jsdelivr.net/npm/chart.js@4.4.7`
- Carbon Charts: `https://cdn.jsdelivr.net/npm/@carbon/charts@1.27.18`
- Font Awesome: `https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0`

### PHP Dependencies
- `dompdf/dompdf` (for PDF generation) — only in composer.json, not used yet

## Documentation Files

- `README.md` — Project overview, highlights, quick start
- `CODEBASE_INDEX.md` — Previously maintained index (outdated)
- `ai_module_analysis.md` — AI module documentation
- `F11-Module.md`, `F12 module.md`, `F13 Module.md` — Module-specific docs
- `Testing_Steps.md` — Test procedures

## Agent-Specific Notes

### Working with AI Module
- AI services use MySQLi-style DB methods; must use `CompatPDOStatement`
- `bootstrap.php` sets up module dependencies
- Central config in `ai/config/ai_central_config.php`
- Providers configured via `modules/ai/providers/`

### Working with Finance Module
- `PeriodCloseService` handles period/year-end closing
- `YearEndClosingService` handles F13 consolidation
- `PostingEngine` posts journal entries to GL
- Financial years managed via `financial_years` table

### Working with RBAC
- Permission format: `module.action`
- Use `hasPermission()` in module files
- `rbac.php` handles normalization
- Admin role has all permissions

### Working with Sessions
- Sessions auto-start in header
- User info in `$_SESSION['user_id']`, `$_SESSION['user_name']`
- Theme in `$_SESSION['theme']`
- Flash messages in `$_SESSION['flash']`

## Troubleshooting

### Database Connection Issues
- Check `config/database.php` credentials
- Verify MySQL service is running
- Check `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` constants

### Routing Issues
- Ensure `mod_rewrite` is enabled in Apache
- Check `.htaccess` syntax
- Verify `RewriteBase` matches your installation path

### Session Issues
- Check PHP session configuration
- Ensure cookies are not blocked
- Verify session directory permissions

### Permission Issues
- Check `roles` and `permissions` tables
- Verify user has role assigned
- Use `check_user_role.php` to debug

### File Access Issues
- Check Apache user permissions on directories
- Verify `.htaccess` rules
- Ensure `allow_url_fopen` is enabled for remote APIs

## Summary

This is a **large, comprehensive ERP system** with:
- **60+ modules** covering all business functions
- **AI-powered features** with 80+ services
- **Complete financial management** (F9, F12, F13)
- **Strong security** with RBAC and input validation
- **Zero dependencies** architecture
- **Production-ready** deployment process

**Key entry points:**
- `index.php` — Dashboard
- `modules/ai/` — AI assistant
- `modules/finance/` — Finance management
- `setup.php` — Initial setup

**Important services to know:**
- `PeriodCloseService` — F12 period/year-end closing
- `YearEndClosingService` — F13 group consolidation
- `AIAgentService` — AI agent management
- `AIDocumentIntelligenceService` — Document processing
- `AIKnowledgeService` — RAG knowledge base
