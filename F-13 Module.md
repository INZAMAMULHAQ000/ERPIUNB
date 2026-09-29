You are a Senior ERP Finance Architect, Chartered Accountant (CA), Group Financial Controller, Consolidation Specialist, and Senior Core PHP Developer.

Finance phases F1 through F12 have been completed and verified.

Now build:

==================================================
PHASE F13 — MULTI-COMPANY, INTERCOMPANY &
FINANCIAL CONSOLIDATION
==================================================

TECH STACK
==================================================

- Core PHP
- MySQL
- HTML
- Tailwind CSS
- Vanilla JavaScript only where required

STRICTLY FORBIDDEN:

- Laravel
- CodeIgniter
- Symfony
- React
- Vue
- Angular
- Next.js
- Any frontend framework
- SPA architecture
- ORM

Use PDO and the existing ERP architecture.

==================================================
OBJECTIVE
==================================================

Extend the Finance system from:

Single Company Accounting

to:

Multi-Company Accounting

and:

Group Financial Consolidation.

Support:

- Multiple legal entities
- Multiple companies
- Company-specific accounting
- Intercompany customers/vendors
- Intercompany transactions
- Intercompany matching
- Intercompany reconciliation
- Intercompany eliminations
- Consolidated Trial Balance
- Consolidated P&L
- Consolidated Balance Sheet
- Group reporting
- Company-level reporting
- Ownership percentages
- Consolidation adjustments

==================================================
CRITICAL ARCHITECTURAL RULE
==================================================

Every accounting transaction must belong to a LEGAL ENTITY / COMPANY.

Do NOT rely only on:

Department

Branch

Location

Project

A company/legal entity is a separate accounting boundary.

==================================================
ACCOUNTING HIERARCHY
==================================================

Group

    ↓

Company A

    ↓

Company B

    ↓

Company C

Each company maintains its own:

Chart of Accounts

General Ledger

Financial Year

Accounting Period

Tax Configuration

Bank Accounts

AR

AP

Assets

Payroll

Expenses

==================================================
STEP 1 — COMPANY STRUCTURE
==================================================

Create or extend:

companies

Fields:

id

company_code

company_name

legal_name

registration_number

tax_number

base_currency

country

timezone

financial_year_start

status

created_at

updated_at

IMPORTANT:

If a Company/Organization master already exists in the ERP:

EXTEND IT.

Do NOT create a duplicate master.

==================================================
STEP 2 — GROUP STRUCTURE
==================================================

Create:

company_groups

Fields:

id

group_code

group_name

parent_company_id

base_currency

status

created_at

updated_at

==================================================
STEP 3 — COMPANY RELATIONSHIPS
==================================================

Create:

company_relationships

Fields:

id

group_id

parent_company_id

child_company_id

ownership_percentage

effective_from

effective_to

relationship_type

status

==================================================
RELATIONSHIP TYPES
==================================================

Parent

Subsidiary

Associate

Branch

Other

Design for future expansion.

==================================================
STEP 4 — COMPANY-SPECIFIC ACCOUNTING
==================================================

Ensure accounting records contain:

company_id

Where applicable.

This includes:

Journal Entries

Journal Lines

AR

AP

Banking

Tax

Assets

Expenses

Payroll

Budgets

Financial Years

Accounting Periods

==================================================
CRITICAL RULE
==================================================

A journal belonging to Company A MUST NOT accidentally affect Company B's General Ledger.

Every posting must validate:

Company

Financial Year

Accounting Period

Chart of Accounts

Currency

==================================================
STEP 5 — INTERCOMPANY ACCOUNTS
==================================================

Create configurable mappings for:

Intercompany Receivable

Intercompany Payable

Intercompany Revenue

Intercompany Expense

Intercompany Loan

Intercompany Interest

Other Intercompany Accounts

Do NOT hard-code account IDs.

==================================================
STEP 6 — INTERCOMPANY PARTNER
==================================================

Allow each company to define another company as an intercompany partner.

Example:

Company A

Partner:

Company B

Company B

Partner:

Company A

==================================================
STEP 7 — INTERCOMPANY TRANSACTIONS
==================================================

Create:

intercompany_transactions

Fields:

id

transaction_number

source_company_id

target_company_id

transaction_date

transaction_type

reference

description

currency

amount

tax_amount

status

created_at

updated_at

==================================================
TRANSACTION TYPES
==================================================

Intercompany Sale

Intercompany Purchase

Intercompany Service

Intercompany Expense

Intercompany Loan

Intercompany Interest

Intercompany Transfer

Other

==================================================
STEP 8 — INTERCOMPANY TRANSACTION FLOW
==================================================

Example:

Company A provides service to Company B.

Company A:

Debit

Intercompany Receivable

Credit

Intercompany Revenue

Company B:

Debit

Intercompany Expense

Credit

Intercompany Payable

Both transactions must be linked.

==================================================
STEP 9 — AUTOMATIC COUNTERPART ENTRY
==================================================

Where appropriate, allow:

Company A creates transaction

↓

System generates corresponding transaction for Company B

↓

Both transactions receive shared:

intercompany_transaction_id

This prevents unmatched transactions.

==================================================
STEP 10 — INTERCOMPANY MATCHING
==================================================

Build matching engine.

Match based on:

Company

Partner Company

Reference

Transaction Date

Currency

Amount

Document Number

Transaction Type

==================================================
MATCH STATUS
==================================================

Matched

Partially Matched

Unmatched

Exception

Resolved

==================================================
STEP 11 — INTERCOMPANY RECONCILIATION
==================================================

Show:

Company A Receivable

Company B Payable

Difference

Difference %

Status

==================================================
EXAMPLE
==================================================

Company A:

Receivable = AED 100,000

Company B:

Payable = AED 100,000

Status:

MATCHED

--------------------------------------------------

If:

Company A = AED 100,000

Company B = AED 95,000

Difference:

AED 5,000

Status:

EXCEPTION

==================================================
STEP 12 — INTERCOMPANY ELIMINATION
==================================================

Build elimination functionality.

During consolidation:

Intercompany Receivable

and

Intercompany Payable

must be eliminated.

Intercompany Revenue

and

Intercompany Expense

must be eliminated.

Do NOT modify the original company ledgers.

Eliminations exist ONLY in the consolidation layer.

==================================================
STEP 13 — ELIMINATION ENTRIES
==================================================

Example:

Company A Revenue:

AED 100,000

Company B Expense:

AED 100,000

Consolidated result should remove:

Revenue AED 100,000

Expense AED 100,000

The original company financial statements remain unchanged.

==================================================
STEP 14 — CONSOLIDATION RUN
==================================================

Create:

consolidation_runs

Fields:

id

group_id

period

financial_year

start_date

end_date

status

created_by

created_at

completed_at

==================================================
CONSOLIDATION STATUS
==================================================

Draft

In Progress

Validated

Approved

Posted

Cancelled

==================================================
STEP 15 — CONSOLIDATION PROCESS
==================================================

Build workflow:

Select Group

↓

Select Companies

↓

Select Financial Year

↓

Select Period

↓

Validate Companies

↓

Validate Intercompany

↓

Load Trial Balances

↓

Currency Conversion

↓

Intercompany Elimination

↓

Consolidation Adjustments

↓

Generate Consolidated Trial Balance

↓

Generate Consolidated P&L

↓

Generate Consolidated Balance Sheet

↓

Validation

↓

Approval

==================================================
STEP 16 — CONSOLIDATED TRIAL BALANCE
==================================================

Show:

Account Code

Account Name

Company A

Company B

Company C

Eliminations

Consolidated

==================================================
STEP 17 — CONSOLIDATED P&L
==================================================

Show:

Company-level amounts

Eliminations

Consolidated amount

Revenue

Cost of Sales

Gross Profit

Operating Expenses

Operating Profit

Other Income

Other Expenses

Profit Before Tax

Tax

Net Profit

==================================================
STEP 18 — CONSOLIDATED BALANCE SHEET
==================================================

Show:

Assets

Liabilities

Equity

Company totals

Eliminations

Consolidated totals

Validate:

Assets

=

Liabilities + Equity

==================================================
STEP 19 — CURRENCY CONVERSION
==================================================

If companies use different currencies:

Support:

Company Currency

Group Currency

Exchange Rate

Translation Date

Translation Method

==================================================
TRANSLATION METHODS
==================================================

Design configuration for:

Closing Rate

Average Rate

Historical Rate

Do not hard-code a single method.

==================================================
STEP 20 — CURRENCY DIFFERENCE
==================================================

Support:

Foreign Currency Translation Adjustment

The adjustment must appear in the appropriate consolidated equity section.

Do NOT alter individual company ledgers.

==================================================
STEP 21 — OWNERSHIP
==================================================

Support ownership percentages.

Example:

Parent owns:

100%

75%

51%

This prepares the system for:

Non-controlling interest.

==================================================
STEP 22 — NON-CONTROLLING INTEREST
==================================================

Design the architecture to calculate:

NCI

based on configured ownership.

Example:

Parent ownership:

80%

Subsidiary profit:

AED 100,000

Potential NCI:

AED 20,000

Do not hard-code this into reports.

==================================================
STEP 23 — CONSOLIDATION ADJUSTMENTS
==================================================

Support:

Intercompany elimination

Currency translation

Reclassification

Manual consolidation adjustment

Other approved adjustment

Each adjustment requires:

Reason

Amount

Account

Company/group

Approval

Audit trail

==================================================
STEP 24 — CONSOLIDATION JOURNAL
==================================================

Consolidation journals MUST NOT modify individual company ledgers.

They belong to the consolidation layer.

Every consolidation journal must reference:

Consolidation Run

Adjustment Type

Source

==================================================
STEP 25 — COMPANY REPORTING
==================================================

Existing financial reports must support:

Company filter

Group filter

Consolidated view

Standalone view

==================================================
STEP 26 — GROUP DASHBOARD
==================================================

Cards:

Group Revenue

Group Expenses

Group Profit

Group Assets

Group Liabilities

Group Equity

Intercompany Balance

Unmatched Transactions

Eliminations

==================================================
STEP 27 — REPORTS
==================================================

Build:

1. Company Trial Balance

2. Group Trial Balance

3. Consolidated Trial Balance

4. Company P&L

5. Consolidated P&L

6. Company Balance Sheet

7. Consolidated Balance Sheet

8. Intercompany Reconciliation

9. Intercompany Exception Report

10. Elimination Report

11. Ownership Report

12. Currency Translation Report

13. Consolidation Adjustment Report

==================================================
STEP 28 — DRILL DOWN
==================================================

Support:

Group Report

↓

Company

↓

Account

↓

Journal

↓

Source Transaction

For intercompany:

Consolidated Amount

↓

Elimination

↓

Company A Transaction

↓

Company B Counterparty Transaction

==================================================
STEP 29 — RBAC
==================================================

Permissions:

company.view

company.create

company.edit

intercompany.view

intercompany.create

intercompany.match

intercompany.reconcile

consolidation.create

consolidation.validate

consolidation.approve

consolidation.post

consolidation.report

==================================================
STEP 30 — AUDIT LOG
==================================================

Track:

Company created

Company relationship created

Intercompany transaction created

Intercompany matched

Intercompany exception resolved

Consolidation started

Consolidation validated

Elimination created

Consolidation approved

Consolidation posted

==================================================
STEP 31 — VALIDATION
==================================================

Before consolidation:

All selected companies must have:

Valid financial year

Valid accounting period

Balanced Trial Balance

No critical posting errors

Intercompany reconciliation completed

Currency rates available

Required consolidation mappings available

==================================================
STEP 32 — IDEMPOTENCY
==================================================

The same consolidation run MUST NOT be posted twice.

The same intercompany transaction MUST NOT generate duplicate counterpart entries.

The same elimination MUST NOT be applied twice.

==================================================
STEP 33 — SECURITY
==================================================

Users must only access companies permitted by their RBAC/company access.

A Company A finance user must not automatically gain access to Company B.

==================================================
STEP 34 — UI
==================================================

Create professional ERP screens:

Company Management

Company Relationships

Intercompany Transactions

Intercompany Matching

Intercompany Reconciliation

Consolidation Runs

Consolidation Workspace

Eliminations

Consolidated Trial Balance

Consolidated P&L

Consolidated Balance Sheet

Consolidation Reports

==================================================
OUTPUT
==================================================

Generate:

1. Company Integration

2. Group Structure

3. Company Relationships

4. Company-Level Accounting Isolation

5. Intercompany Accounts

6. Intercompany Transactions

7. Counterparty Transactions

8. Matching Engine

9. Reconciliation

10. Elimination Engine

11. Consolidation Runs

12. Currency Translation

13. Ownership

14. NCI-ready architecture

15. Consolidated Trial Balance

16. Consolidated P&L

17. Consolidated Balance Sheet

18. Consolidation Reports

19. Dashboard

20. RBAC

21. Audit Logging

22. Validation

23. Tailwind UI

==================================================
CRITICAL RULE
==================================================

NEVER modify a company's standalone accounting records merely to produce consolidated reporting.

The architecture must remain:

Company Ledger

+

Company Ledger

+

Company Ledger

+

Consolidation Layer

=

Group Financial Statements

==================================================
DO NOT BUILD
==================================================

❌ Manufacturing

❌ Inventory

❌ Procurement

❌ CRM

❌ Payroll calculation

❌ Tax filing

❌ Audit management

Those belong to other ERP phases.