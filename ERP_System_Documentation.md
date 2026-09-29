# ERP System — Professional Documentation

## Executive Summary

**Enterprise Resource Planning System** — A modular, production-ready ERP built with **vanilla PHP 8.1+** and **MySQL**. Zero dependencies architecture (no Composer, no framework lock-in) with **60+ integrated modules** covering HR, payroll, finance, procurement, CRM, projects, helpdesk, and AI-powered automation.

**Tech Stack:**
- PHP 8.1+ (vanilla, zero dependencies)
- MySQL with PDO prepared statements
- Tailwind CSS (CDN-based, no build step)
- Chart.js 4 + Carbon Charts for analytics
- Apache mod_rewrite for routing

---

## Core Architecture

### Request Flow

1. **Entry Point:** `index.php` (dashboard) or module routes via `.htaccess` rewrite
2. **Authentication:** `middleware/auth.php` requires login via `includes/header.php`
3. **RBAC Check:** `helpers/rbac.php` validates permissions before allowing access
4. **Module Execution:** Module's `index.php` or action file executes
5. **Template Rendering:** `includes/header.php` → Module content → `includes/footer.php`

### Routing System

- **Clean URLs:** `.htaccess` rewrites `/modules/users/index` to `/modules/users/index.php`
- **Auto-Append:** Missing extensions automatically added (e.g., `users` → `users.php`)
- **File Access Denied:** Config, helpers, templates, `.sql`, `.md`, `.env`, `_debug` blocked

---

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
├── *.php / *.sql / *.md    # Entry points, migrations, documentation
```

---

## Module Structure

Each module in `modules/` is self-contained with:
- `index.php` — List view (table/grid)
- `create.php` — Create form
- `edit.php` — Edit form
- `view.php` — Detail view
- `delete.php` — Delete action
- Subdirectories for complex modules

### Key Modules

| Module | Key Features |
|--------|-------------|
| **finance** | chart_of_accounts, journal_entries, general_ledger, budgets, payroll, period_close (F12), intercompany (F13), fixed_assets (F9), tax, 20+ reports |
| **ai** | 80+ services (AIAgentService, AIDocumentIntelligenceService, AIKnowledgeService, AIWorkflowService, AIGovernanceService), knowledge base with RAG, multiple AI providers, governance (policy enforcement, kill switches) |
| **attendance** | Check-in/check-out, time tracking per project |
| **payroll** | Payroll processing with tax, deductions, benefits |
| **leads/clients/deals** | CRM pipeline tracking |
| **procurement** | RFQ, purchase orders, requisitions, quotations |
| **projects/tasks** | Project lifecycle, Kanban boards, milestones, timesheets |
| **tickets/knowledge_base** | Issue tracking with SLA |

### Finance Module (`modules/finance/`)

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

### AI Module (`modules/ai/`)

- `services/` — 80+ AI services
- `workflows/` — AI workflow orchestration
- `knowledge/` — Knowledge base with vector search (RAG)
- `integrations/` — External system integrations
- `governance/` — AI safety, policy enforcement, observability
- `copilot/` — AI copilot interface
- `agents/` — AI agents for tasks
- `providers/` — AI model providers (OpenAI, Anthropic, etc.)
- `analytics/` — AI-powered analytics

---

## Core Architecture Patterns

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
- RBAC system normalizes permissions

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

---

## AI Module Architecture

### Service Layer (80+ services)

Located in `modules/ai/services/`:

| Service | Description |
|---------|-------------|
| `AIAgentService.php` | Agent management |
| `AIDocumentIntelligenceService.php` | Document processing (OCR, extraction) |
| `AIKnowledgeService.php` | Knowledge base with vector search |
| `AIWorkflowService.php` | Workflow orchestration |
| `AIGovernanceService.php` | Policy enforcement, rate limits, cost controls |
| `AIIntegrationService.php` | External API integration |
| 70+ more specialized services | Various AI capabilities |

### Governance Features

- `AIGovernanceMiddleware.php` — Request validation
- `AIPolicyEngine.php` — Rule enforcement
- `AIKillSwitchService.php` — Emergency shutdown
- `AIUsageService.php` — Usage tracking
- `AIMetricRegistry.php` — Metrics collection

### Knowledge Base (RAG)

- Vector store integration for semantic search
- Q&A generation and content moderation

### API Endpoints

- `agents.php`, `tasks.php`, `workflows.php`, `knowledge.php`
- `chat.php`, `copilot.php`, `management.php`, `governance.php`
- `integrations.php`, `analytics.php`, `process_intelligence.php`

---

## Testing Approach

### Test Suite Structure

Tests are standalone PHP files that:
1. Create isolated test data (TEST-FY-F12, TEST-FY-F13, etc.)
2. Run formal test procedures
3. Restore database to original state afterward

### Formal Test Suites

| Test Suite | Description | Tests |
|------------|-------------|-------|
| **F12** | Finance Period Closing | 11 formal tests |
| **F13** | Group Consolidation | Multicompany with eliminations |
| **F9** | Fixed Assets | Asset lifecycle management |
| **P1** | Procurement RFQ | RFQ processing |
| **P2** | Procurement PO | Purchase orders |
| **P3** | Procurement PO3 | PO processing |

### Check Scripts

- `check_asset_categories.php`
- `check_users.php`
- `check_kill_switches.php`
- `check_tables.php`

### Test Data Isolation

- Creates isolated test data per suite
- Restores database to original state afterward

---

## Migration System

- Migrations run in order (001 → 049+)
- `config/runner.php` executes all migrations
- Creates new tables and alters existing ones
- **One-way execution** (no rollback mechanism)

### Migration Files

50+ migration files covering:
- Dashboard customization
- Finance foundation
- Tax management
- Fixed assets (F9)
- Period close (F12)
- Group consolidation (F13)
- AI modules (F11-F34)
- Inventory modules (I1-I3)

---

## Security Considerations

### Input Validation

- All user input validated via `helpers/validation.php`
- `sanitize()` function used for XSS prevention (htmlspecialchars with ENT_QUOTES)
- Prepared statements for all SQL queries

### Directory Protection

`.htaccess` blocks access to:
- `config/` (database credentials)
- `helpers/` (core utilities)
- `middleware/` (authentication)
- `templates/` (UI components)
- `.sql`, `.md`, `.env`, `.jsonl` files
- `_debug` paths

### Session Security

- Session cookie flags: HttpOnly, Secure, SameSite
- Session regeneration on login/logout
- Session timeout configuration

### Rate Limiting

- Not built-in; should be implemented at web server level (Apache/Nginx)

---

## Deployment Considerations

### Requirements

- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.3+
- Apache with mod_rewrite enabled
- `allow_url_fopen` enabled (for remote APIs)

### Configuration Steps

1. Set database credentials in `config/database.php`
2. Import schema via CLI or browser
3. Run migrations via `config/runner.php`
4. Seed data via `config/seed_data.php` (optional)
5. Set up cron jobs for schedulers

### Environment Detection

- **Local:** localhost, 127.0.0.1, ::1
- **Production:** All other hosts (hides errors)

### Performance Optimization

- Database indexes on foreign keys and frequently queried columns
- Pagination on list views (in helpers)
- AJAX for dynamic operations
- CSS/JS minification recommended for production

---

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

---

## External Dependencies

### CDNs

- Tailwind CSS: `https://cdn.tailwindcss.com`
- Chart.js: `https://cdn.jsdelivr.net/npm/chart.js@4.4.7`
- Carbon Charts: `https://cdn.jsdelivr.net/npm/@carbon/charts@1.27.18`
- Font Awesome: `https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0`

### PHP Dependencies

- `dompdf/dompdf` (for PDF generation) — only in composer.json, not used yet

---

## Documentation Files

- `README.md` — Project overview, highlights, quick start
- `CODEBASE_INDEX.md` — Previously maintained index (outdated)
- `ai_module_analysis.md` — AI module documentation
- `F11-Module.md`, `F12 module.md`, `F13 Module.md` — Module-specific docs
- `Testing_Steps.md` — Test procedures

---

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

---

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

---

## Summary

**This is a large, comprehensive ERP system with:**

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
- `AIGovernanceService` — AI safety and compliance

---

*Document generated from codebase analysis of ERP system at C:\xampp\htdocs\111*