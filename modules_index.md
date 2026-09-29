# modules/ Index

Workspace: `C:\xampp\htdocs\111`  
Indexed: modules/ directory only (self-contained feature modules)

---

## Module Overview (52 modules)

| Module | Files | Subdirs | Type / Domain |
|---|---|---|---|
| activity_logs | 2 | 0 | Logging / Audit |
| ai | 2 | 31 | AI / Intelligence (203 files total) |
| analytics | 2 | 0 | Reporting / BI |
| appearance | 1 | 0 | UI / Theme |
| asset_assignments | 2 | 0 | Asset Management |
| asset_categories | 1 | 0 | Asset Management |
| asset_returns | 1 | 0 | Asset Management |
| assets | 4 | 0 | Fixed Assets (F9) |
| assets_dashboard | 1 | 0 | Dashboard |
| attendance | 3 | 0 | HR / Time |
| audit_logs | 1 | 0 | Audit |
| auth | 2 | 0 | Authentication |
| clients | 3 | 0 | CRM |
| crm_dashboard | 1 | 0 | Dashboard |
| deals | 3 | 0 | CRM / Sales Pipeline |
| departments | 4 | 0 | HR / Org |
| designations | 4 | 0 | HR / Job Titles |
| employees | 6 | 0 | HR |
| executive_dashboard | 1 | 0 | Dashboard |
| expense_categories | 1 | 0 | Finance |
| expenses | 4 | 0 | Finance |
| finance | 3 | 52 | Finance / ERP Core (F9/F12/F13) |
| helpdesk_dashboard | 1 | 0 | Dashboard |
| holidays | 3 | 0 | HR |
| hrms_dashboard | 1 | 0 | Dashboard |
| inventory | 19 | 0 | Inventory / Stock |
| invoices | 4 | 0 | Billing |
| knowledge_base | 5 | 0 | Helpdesk / KB |
| leads | 4 | 0 | CRM |
| leave_management | 4 | 0 | HR |
| leave_requests | 5 | 0 | HR |
| leave_types | 3 | 0 | HR |
| licenses | 2 | 0 | IT / Software |
| maintenance | 2 | 0 | Maintenance |
| menu_manager | 3 | 0 | Navigation |
| payments | 3 | 0 | Finance |
| payroll | 1 | 0 | HR / Payroll |
| people_management | 5 | 0 | HR |
| permissions | 4 | 0 | RBAC / Security |
| procurement | 7 | 9 | Procurement / P1-P3 |
| projects | 3 | 0 | Project Mgmt |
| projects_dashboard | 1 | 0 | Dashboard |
| reports | 6 | 0 | Reporting |
| roles | 5 | 0 | RBAC |
| scheduled_reports | 2 | 0 | Reporting |
| slas | 4 | 0 | Helpdesk / SLA |
| tasks | 4 | 1 | Projects / Tasks |
| tax_settings | 1 | 0 | Finance / Tax |
| template_designer | 6 | 4 | Template / Forms |
| ticket_categories | 4 | 0 | Helpdesk |
| tickets | 6 | 0 | Helpdesk |
| timesheets | 2 | 0 | HR / Time Tracking |
| users | 5 | 0 | User Management |
| vendors | 1 | 0 | Procurement |
| widgets | 1 | 0 | Widgets / UI Components |

---

## Deep Index — Large / Complex Modules

### ai (31 subdirs, 203 files)
Submodules: `agents`, `analytics`, `api`, `assistant`, `automation`, `automations`, `command-center`, `communications`, `config`, `copilot`, `documents`, `governance`, `includes`, `insights`, `integrations`, `intelligence`, `knowledge`, `management`, `models`, `monitoring`, `observability`, `process-intelligence`, `providers`, `services`, `settings`, `skills`, `tasks`, `tools`, `voice`, `workflows`, `workspaces`.
Purpose: AI-powered automation, RAG knowledge base, agent orchestration, governance, copilot, document intelligence.

### finance (52 subdirs, high file count)
Key submodules: `chart_of_accounts`, `journal_entries`, `general_ledger`, `budget_*`, `payroll`, `period_close`, `intercompany`, `fixed_assets`, `tax`, `reports`, `bank_reconciliation`, `cash_transactions`, `vendor_bills`, `customer_invoices`, `expense_approvals`, `posting_engine`, `opening_balances`, `financial_year`.
Purpose: Full double-entry bookkeeping, period closing (F12), year-end (F13), fixed assets (F9), budgets, intercompany elimination.

### procurement (9 subdirs, 7 root files)
Submodules: `rfq`, `quotations`, `requisitions`, `purchase_orders`, `vendor_master`, `vendor_bills`, `vendor_payments`, `vendor_statements`, `goods_receipt`.  
Purpose: RFQ, quotation comparison, requisitions, purchase orders, vendor management.

### inventory (19 root files, 0 subdirs)
Purpose: Stock tracking, goods receipt, inventory control.

### templates / tasks (subdirs present)
- `tasks`: 1 subdir (likely Kanban/task-board structure)
- `template_designer`: 4 subdirs (form/template design)

---

## Module Patterns

Standard CRUD file patterns observed:
- `index.php` (list)
- `create.php` / `edit.php` / `view.php` / `delete.php`
- Subdir modules contain nested actions (e.g., `finance/period_close/`, `procurement/rfq/`)

---

## Key Entry Points Referenced in AGENTS.md
- `index.php` → Dashboard
- `modules/ai/` → AI assistant
- `modules/finance/` → Finance management
- `modules/procurement/` → Procurement (P1/P2/P3)
- `modules/attendance/` → HR attendance
- `modules/payroll/` → Payroll
- `modules/projects/` → Project management
- `modules/reports/` → Custom reports / scheduled reports

---

*Index generated from directory scan. For full file-level mapping, see `modules_index.txt` (stats output) or run a deeper recursive file list.*
