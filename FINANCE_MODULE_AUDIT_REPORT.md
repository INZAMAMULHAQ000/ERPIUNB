# Finance Module Audit Report

Date: 2026-09-14
Scope: `modules/finance/`, finance migrations, live `erp_db` schema/data smoke checks.

## Executive Summary

The finance module has the right high-level shape for a professional ERP finance system, but it is currently a wide collection of CRUD screens and repeated posting snippets rather than a controlled accounting product. The accounting spine exists:

- Chart of Accounts, financial years, voucher types
- Journal header/lines
- Posting engine
- General ledger
- Period close lock checks
- AR/AP/expenses/banking documents that can create journals

The most important design correction is not to add more screens. It is to make every source document follow one governed lifecycle:

`Draft document -> validate setup -> approve/post -> immutable GL -> allocate/settle -> reverse via controlled document action -> report/reconcile/close`

At the moment, too many modules directly create `journal_entries` and `journal_entry_details` themselves. They mostly call `PostingEngine::post()`, which is good, but the accounting rules are scattered across AR, AP, expenses, banking, payroll, tax, and period close. This is why the module feels meaningless: there is no single workflow contract.

## What Is Already Good

- No finance submodule was found writing directly to `general_ledger`; only `PostingEngine` inserts GL rows.
- Live DB has core setup data: 39 chart accounts, 31 voucher types, 20 journal entries, 40 GL rows, 7 customer invoices, 2 vendor bills, 1 bank account, 1 cash account.
- Finance settings are mostly configured: AR, AP, revenue, expense, tax, bank/cash mappings exist.
- F12 period lock logic is present in `PostingEngine`.
- F13 multicompany migration adds `company_id` to `financial_years`, `journal_entries`, and `general_ledger`, so the current schema can support company-stamped accounting.
- Tax service is centralized and supports `tax_*` settings, tax periods, and tax register entries.

## Critical Issue Fixed

`PostingEngine::post()` used `logAudit()` but did not load the helper that defines it. In service/CLI/API contexts this caused posting to fail after GL insert logic was reached.

Fixed:

- `modules/finance/posting_engine/PostingEngine.php` now loads `helpers/security.php` before `finance_helpers.php`.
- Rollback smoke test passed: a balanced journal posted successfully and created 2 GL rows inside a rolled-back transaction.
- `modules/finance/customer_receipts/create.php` now loads invoice `status` during locked allocation updates and includes `approved` invoices as collectible.
- `modules/finance/customer_receipts/allocate.php` now also includes `approved` invoices for later receipt allocation.

## Critical Risks Still Present

1. Posting rules are duplicated in many files.

   Examples include customer invoice approval, receipts, vendor bills, vendor payments, expenses, claims, petty cash, bank/cash transactions, tax adjustments, payroll, and period close. They each assemble journals independently.

   Required fix: introduce a `FinanceDocumentPostingService` or `AccountingDocumentService` that receives a source document type and returns/post the journal lines from a controlled map.

2. Source document lifecycle is inconsistent.

   AR invoice statuses include `draft`, `approved`, `sent`, `partially_paid`, `paid`, `overdue`, `cancelled`, but approval posts GL while receipts only load invoices with `sent`, `partially_paid`, or `overdue`. This means a freshly approved invoice is posted but cannot be paid until separately sent. That is acceptable only if "Send" is a required business state, but the UI/reporting must make it obvious.

3. Some code reads columns it did not select.

   `customer_receipts/create.php` updates invoice status history using `$inv['status']`, but its locked invoice query selects only `total_amount`, `paid_amount`, and `balance_amount`. This will produce undefined index behavior and unreliable history.

4. Opening balances are not part of the accounting spine.

   COA stores `opening_balance`, and `opening_balance_history` exists, but a professional finance system should convert opening balances into an opening journal per financial year and make trial balance reconcile from GL, not from loose balances.

5. Running balance calculation is too naive.

   `PostingEngine` calculates running balance as `last + debit - credit` for all account natures. That is acceptable for a signed ledger, but reports must consistently interpret credit-nature accounts. The unused `$nature` lookup indicates the original intent was not completed.

6. Finance helper duplicates core helpers.

   `modules/finance/helpers/finance_helpers.php` defines local fallback versions of `sanitize`, `url`, `hasPermission`, etc. This can hide real RBAC/security behavior if load order changes. Finance should consume core helpers, not redefine them.

7. Legacy finance screens should be omitted from the professional module.

   Root-level/legacy `invoices`, `payments`, `expenses`, and `schema_finance.sql` style tables should not remain active in the main finance workflow. Keep read-only migration tools if needed; otherwise hide them under "Legacy" until removed.

## Module-by-Module Verdict

### Dashboard

Keep, but rebuild around finance health:

- Unposted journals
- Unpaid AR/AP
- Bank/cash balances
- Period close status
- Trial balance imbalance warnings
- Missing required account mappings

Do not make it a decorative KPI page only.

### Foundation Setup

Keep and strengthen.

- Financial years: must generate accounting periods automatically.
- Chart of accounts: must enforce account nature, control-account flags, manual-entry restrictions, and mapping completeness.
- Account groups/types: keep, but reduce confusion. Account groups define statement nature; account types should drive behavior such as bank, cash, AR, AP, tax, revenue, expense.
- Opening balances: move from loose COA values to controlled opening balance journal.

### Journals & Ledger

Keep. This is the accounting core.

Required:

- Journal view page should exist as immutable posted voucher view.
- Draft journals may be edited; posted journals only reversed.
- Add source document reference columns or a source reference table: source_module, source_id, source_number.
- Add posting validation report before posting.
- Ledger must reconcile to trial balance and source subledgers.

### Banking & Cash

Keep, but split operational banking from accounting.

- Bank/cash account setup is valid.
- Bank/cash transactions currently create journals manually; move to posting service.
- Money transfers should post DR destination, CR source and prevent same-account transfer.
- Reconciliation should match GL/bank transactions and store statement lines, not only summary differences.
- Petty cash should be a cash subledger with custodian, imprest limit, top-up, issue, expense, and settlement.

### Accounts Receivable

Keep as first stabilization target.

Professional workflow:

1. Create customer invoice as draft.
2. Validate customer, line amounts, tax, revenue/AR mappings.
3. Approve invoice and post: DR Accounts Receivable, CR Revenue, CR Output Tax.
4. Send invoice or mark available for collection.
5. Record receipt: DR Bank/Cash, CR Accounts Receivable.
6. Allocate receipt to invoice.
7. Invoice becomes paid/partially paid.
8. Customer statement and AR aging reconcile to GL AR control.
9. Credit note reverses revenue/tax/AR and applies to invoice.

Current status: approval and receipt workflow are close. The immediate blocker where approved invoices were not collectible has been corrected. The next improvement is to make the approved/sent business rule explicit in UI labels and reports.

### Accounts Payable

Keep, but decide ownership with procurement.

- Vendor master belongs shared across procurement/AP.
- Purchase orders and goods receipts should ideally live in procurement, with AP consuming approved PO/GRN for 2-way/3-way matching.
- Vendor bill approval should post DR Expense/Input Tax/Inventory or GRNI, CR AP.
- Vendor payment should post DR AP, CR Bank/Cash.
- Debit note should reduce AP and reverse expense/tax correctly.

Current issue: AP includes its own PO/GRN while procurement modules also exist. Choose one canonical procurement source and let finance AP handle bills/payments/accounting.

### Expense Management

Keep, but simplify.

- Company expenses: business expense paid by company or payable first.
- Employee claims: employee spends personally, manager approves, finance approves, reimbursement pays employee.
- Reimbursements: must settle employee payable.
- Approvals: useful but should be driven by one approval history/workflow pattern.

Required accounting:

- Expense approval: DR Expense/Input Tax, CR Expense Payable.
- Payment/reimbursement: DR Expense Payable, CR Bank/Cash.

### Budget Management & Cost Control

Keep, but not in the first transaction path.

- Budgets, versions, revisions, transfers, monitoring, and reports are valid.
- It should not post to GL.
- It should optionally block/warn source documents before approval.
- Cost centers should be first-class if budget control is required.

### Fixed Assets F9

Keep, but only after core GL stabilizes.

Required professional workflow:

- Capitalize asset from vendor bill or acquisition.
- Post DR Fixed Asset/Input Tax, CR AP/Bank.
- Monthly depreciation posts DR Depreciation Expense, CR Accumulated Depreciation.
- Disposal posts cash/proceeds, accumulated depreciation, asset cost, gain/loss.

Current signal: many fixed asset files exist, but no files in this submodule call `PostingEngine::post()`. That means asset accounting may be incomplete or decoupled from GL.

### Payroll Finance F10

Keep one version only.

There are both `payroll/` and `payroll_finance/` finance areas. This should be consolidated into one payroll finance integration:

- payroll accrual posting
- employee payable
- deductions payable
- employer contributions
- payment batch
- reconciliation

Current issue: duplicate folders and test/debug files (`test*.php`, `.mjs`, `debug.php`, `.bak`) should not be in the production menu.

### Financial Reporting F11

Keep and make it read-only from GL/subledgers.

Required reports:

- Trial balance
- General ledger
- Account ledger
- Profit and loss
- Balance sheet
- Cash flow
- AR/AP aging
- Tax report
- Integrity checks

Reports must not invent balances. They should derive from posted GL plus controlled opening balances and reconcile subledgers.

### Period & Year-End Close F12

Keep. This is necessary for professional finance.

Required:

- Period generation per financial year
- Checklist by period/year
- Block postings to closed periods
- Closing adjustments
- Year-end closing journal
- Opening balances into next year
- Controlled reopen with audit

Current design is promising. It should become the lock around all posting paths.

### Group & Consolidation F13

Keep, but isolate from single-company MVP.

It is advanced and useful, but it depends on clean company-stamped GL. Do not let it complicate the first working transaction. Stabilize one company first, then turn on consolidation.

### Tax

Keep. Tax should be a shared service, not repeated document math.

Required:

- Tax types/rates/groups/exemptions
- Tax settings with input/output/adjustment accounts
- Tax transactions register
- Tax periods/submission/close
- Tax reports reconciled to GL tax accounts

### Tools & Settings

Keep only professional settings.

Must keep:

- base currency
- account mappings
- tax mappings
- numbering/settings
- close settings

Omit/hide:

- legacy invoices/payments/expenses from primary menu
- debug/test files
- duplicate generated migration artifacts from operational documentation

## Recommended First Complete Transaction

Use AR invoice-to-cash as the first complete meaningful transaction because it has the shortest business path and the live DB already has most prerequisites.

Target transaction:

1. Create customer invoice.
2. Approve invoice.
3. Post sales journal:
   - DR Accounts Receivable
   - CR Revenue
   - CR Output Tax, if taxable
4. Mark invoice sent or make approved invoices collectible.
5. Record customer receipt to bank/cash.
6. Allocate receipt to invoice.
7. Post receipt journal:
   - DR Bank/Cash
   - CR Accounts Receivable
8. Confirm:
   - invoice status is paid
   - customer statement shows invoice and receipt
   - AR aging no longer includes the paid balance
   - GL has both posted vouchers
   - trial balance remains balanced
   - AR control equals AR subledger

## Implementation Plan

Phase 1: Stabilize the accounting spine.

- Ensure all finance helpers load core helpers safely.
- Add/standardize source document metadata for journals.
- Fix receipt allocation status-history bug.
- Add a posting smoke test script that runs inside rollback.
- Add setup health check: financial year, periods, account mappings, tax mappings, bank/cash mappings.

Phase 2: Complete AR invoice-to-cash.

- Create one service for AR invoice approval posting.
- Create one service for receipt posting/allocation.
- Fix invoice status transitions: decide whether `approved` is collectible or require `sent`.
- Add customer statement and AR aging reconciliation checks.
- Add test: invoice -> approve/post -> receipt -> allocate -> paid -> GL balanced.

Phase 3: Apply same pattern to AP.

- Decide canonical procurement ownership.
- Vendor bill posting service.
- Vendor payment posting/allocation service.
- AP aging and vendor statement reconciliation.

Phase 4: Expense and cash/bank.

- Company expense approval/payment service.
- Employee claim approval/reimbursement service.
- Bank/cash transaction service.
- Reconciliation upgrade.

Phase 5: Reporting/close hardening.

- Trial balance must be the truth check.
- Integrity report must compare GL control accounts to AR/AP/tax/assets subledgers.
- Period close should block every posting path.

## Immediate Next Fixes

1. Add source metadata to journals or a journal-source reference table.
2. Extract AR posting logic from page controllers into service classes.
3. Make approved/sent collection status explicit in UI and reports.
4. Hide legacy finance menu items and production-test/debug files.
5. Add rollback tests for AR invoice-to-cash.

## Final Direction

The correct professional finance module should not be many screens. It should be a controlled accounting engine with modules feeding it. Keep the module tree, but make GL posting, reversal, period locks, tax, source references, and reconciliation non-negotiable rules.

First milestone: one complete AR invoice-to-cash transaction that can be demonstrated end to end and reconciles in GL, AR aging, customer statement, and trial balance.
