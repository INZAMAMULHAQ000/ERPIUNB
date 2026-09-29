# Codebase Index

## Root Directory
- **Entry Points**: `index.php`, `launchpad.php`, `setup.php`
- **Configuration**: `.gitignore`, `.htaccess`, `composer.json`, `composer.lock`
- **Core Config**: `/config` (database, migrations, schemas, seeds)
- **Assets**: 
  - `/assets/css` (dashboard.css, ibm-carbon.css, light-m365.css, login.css, style.css)
  - `/assets/js` (carbon-charts.js, dashboard-core.js, fluent-dropdown.js, theme.js)
  - `/templates` (header.php, footer.php, navbar.php, sidebar.php, mock_preview.php)
- **Helpers**: `/helpers` (utility functions: currency, finance_helpers, menu, pagination, rbac, security, template_renderer, url, validation)
- **Middleware**: `/middleware/auth.php` (authentication)
- **API**: `/api` (REST endpoints matching business modules)
- **Widgets**: `/widgets/widget_base.php` (base widget class)
- **Miscellaneous**: Debug scripts, SQL migrations, documentation, test scripts

## Modules Directory Structure
Each module follows a consistent pattern with CRUD operations and specialized functionality:

### Core Business Modules
1. **Finance** (`/modules/finance/`)
   - Sub-modules: account_groups, account_types, aging_reports, ap_aging, ar_dashboard, bank_accounts, bank_reconciliation, bank_transactions, budget_monitoring, budget_reports, budget_revisions, budget_transfers, budget_versions, budgets, cash_accounts, cash_transactions, chart_of_accounts, company_expenses, credit_notes, customer_invoices, customer_receipts, customer_statements, debit_notes, employee_claims, expense_approvals, expense_categories, expense_reimbursements, expense_reports, financial_year, fixed_assets, goods_receipt, group, helpers, index.php, journal_entries, ledger, money_transfers, opening_balances, payroll, payroll_finance, period_close, petty_cash, posting_engine, procurement_dashboard, purchase_orders, reports, settings, tax, vendor_bills, vendor_master, vendor_payments, vendor_statements, voucher_types
   - Service Classes: `PayrollProcessor.php`, `PayrollReportGenerator.php`, `PostingEngine.php`, `TaxService.php`, `PeriodCloseService.php`, `YearEndClosingService.php`, `CompanyService.php`, `ConsolidationService.php`, `FinancialReportService.php`, `PoService.php`, `ProcurementService.php`, `RfqService.php`

2. **Human Resources**
   - Employee management, leave requests, payroll integration, HR dashboard

3. **CRM**
   - Client management, deal tracking, lead management, CRM dashboard

4. **Procurement**
   - Purchase orders, RFQs, vendor management, procurement dashboard

5. **Inventory & Assets**
   - Asset tracking, inventory control, fixed assets module

6. **Operations**
   - Tasks & project management, time tracking, helpdesk/ticketing, knowledge base

### Specialized Modules
1. **AI Assistance Module** (`/modules/ai/` and `/AI-Assistance-Module/`)
   - Agents, API endpoints, assistant, automation services, command center, configuration, insights, knowledge, management, models, monitoring, providers, services, settings, tasks, tools

2. **Analytics & Reporting**
   - Carbon footprint tracking, comprehensive financial reporting

3. **Design & Customization**
   - Template designer, appearance/theming

4. **Administration**
   - Roles, permissions, settings, audit logs, etc.

## Database Layer
- **Schema**: `/config/schema.sql` and domain-specific schemas
- **Migrations**: Versioned SQL files (001_* to 027_*) covering all modules
- **Seeds**: Initial data and sample datasets

## Technical Patterns
- **Modular Architecture**: Isolated directories with standardized CRUD files
- **Service Layer**: Encapsulated business logic in service classes
- **API-First Design**: RESTful endpoints mirroring module structure
- **Theme Integration**: Carbon Design System + Fluent UI influences
- **Migration-Based DB Evolution**: Version-controlled schema changes
- **Security**: Prepared statements, permission checks, input validation
- **Error Handling**: Consistent flash messaging and user feedback

## Estimated Scope
- ~730 PHP files (excluding tests, assets, configs)
- 30+ core business modules
- Extensive SQL schema for finance, HR, CRM, inventory, projects, AI, etc.
- Modern frontend assets with responsive design
- Service-oriented architecture with clear separation of concerns
- Enterprise-grade feature set for mid-large organizations

This index provides a comprehensive structural overview of the entire codebase without modifying any files.