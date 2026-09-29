You are a Senior ERP Finance Architect, Chartered Accountant (CA), Financial Controller, and Senior Core PHP Developer.

Finance phases F1 through F11 have been completed and verified.

Now build:

==================================================
PHASE F12 — ACCOUNTING PERIOD & YEAR-END CLOSING
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

Build a professional Accounting Period and Financial Year Closing system.

Support:

- Financial years
- Accounting periods
- Period opening
- Period closing
- Period locking
- Period reopening
- Year-end closing
- Temporary account closing
- Retained earnings
- Opening balance carry-forward
- Closing balance verification
- Year-end adjustments
- Closing checklist
- Closing audit trail

==================================================
CRITICAL ACCOUNTING RULE
==================================================

NEVER:

❌ Delete posted transactions

❌ Rewrite historical journal entries

❌ Directly alter ledger balances

❌ Permanently remove old financial years

❌ Modify historical transactions to make reports balance

Corrections MUST use:

- Reversal
- Adjustment
- Corrective journal

==================================================
ACCOUNTING FLOW
==================================================

Current Financial Year

        ↓

Period-End Review

        ↓

Reconciliation

        ↓

Adjustments

        ↓

Period Close

        ↓

Year-End Closing

        ↓

Temporary Accounts Closed

        ↓

Retained Earnings

        ↓

Permanent Balance Sheet Accounts Carried Forward

        ↓

New Financial Year

==================================================
MODULES
==================================================

1. Financial Year Management

2. Accounting Period Management

3. Period Closing

4. Period Reopening

5. Closing Checklist

6. Year-End Closing

7. Temporary Account Closing

8. Retained Earnings

9. Opening Balance Carry Forward

10. Closing Adjustments

11. Closing Audit Trail

12. Closing Reports

==================================================
STEP 1 — FOLDER STRUCTURE
==================================================

Create:

modules/

finance/

period_close/

    financial_years/

    periods/

    checklist/

    reconciliation/

    adjustments/

    closing/

    opening_balances/

    reports/

==================================================
STEP 2 — FINANCIAL YEARS
==================================================

Create/manage:

Financial Year

Fields:

id
year_code
name
start_date
end_date
status
closed_by
closed_at
created_at
updated_at

Statuses:

Open

Closing

Closed

Archived

==================================================
STEP 3 — ACCOUNTING PERIODS
==================================================

Create periods within a financial year.

Example:

January

February

March

...

December

Fields:

id
financial_year_id
period_name
start_date
end_date
status
closed_by
closed_at
created_at
updated_at

Statuses:

Open

Closing

Closed

==================================================
STEP 4 — PERIOD VALIDATION
==================================================

Before closing a period verify:

No invalid journal entries

Debits = Credits

Required reconciliations completed

No pending critical approvals

Bank reconciliation status

AR reconciliation status

AP reconciliation status

Tax reconciliation status

Payroll reconciliation status

Fixed Asset reconciliation status

==================================================
STEP 5 — PERIOD LOCKING
==================================================

When a period is CLOSED:

Prevent:

New journal posting

Invoice posting

Vendor bill posting

Receipt posting

Payment posting

Expense posting

Payroll posting

Depreciation posting

Tax posting

Any other accounting transaction

unless explicitly allowed through an authorized adjustment process.

==================================================
STEP 6 — PERIOD REOPENING
==================================================

Allow authorized finance administrators to reopen a closed period.

Require:

Reason

User

Timestamp

Approval

Audit trail

DO NOT silently reopen.

==================================================
STEP 7 — CLOSING CHECKLIST
==================================================

Create configurable closing checklist.

Examples:

Bank reconciliation completed

AR reconciled

AP reconciled

Tax reconciled

Payroll reconciled

Fixed assets reconciled

Inventory reconciled

Accruals reviewed

Prepayments reviewed

Suspense accounts reviewed

Trial Balance reviewed

P&L reviewed

Balance Sheet reviewed

Management approval

==================================================
STEP 8 — RECONCILIATION STATUS
==================================================

Each checklist item must have:

Pending

In Progress

Completed

Exception

Allow comments.

Allow attachment of supporting documents.

==================================================
STEP 9 — PERIOD-END ADJUSTMENTS
==================================================

Support authorized adjustment journals.

Examples:

Accrual

Prepayment

Provision

Correction

Depreciation adjustment

Tax adjustment

Other closing adjustment

All adjustments MUST use the Posting Engine.

==================================================
STEP 10 — YEAR-END VALIDATION
==================================================

Before closing a financial year verify:

All periods closed

Trial Balance balanced

Bank reconciled

AR reconciled

AP reconciled

Tax reconciled

Payroll reconciled

Fixed Assets reconciled

Suspense accounts reviewed

Required adjustments posted

No unresolved critical exceptions

Profit & Loss reviewed

Balance Sheet reviewed

==================================================
STEP 11 — TEMPORARY ACCOUNTS
==================================================

Identify temporary accounts.

Typically:

Revenue

Cost of Sales

Operating Expenses

Other Income

Other Expenses

Tax/Profit-related temporary accounts according to configuration.

Permanent accounts such as:

Assets

Liabilities

Equity

must carry forward.

==================================================
STEP 12 — YEAR-END CLOSING
==================================================

At year end:

Calculate Net Profit / Loss.

Close temporary income statement accounts according to the configured closing method.

Transfer resulting profit/loss to:

Retained Earnings

The exact retained earnings account MUST be configurable.

Do NOT hard-code an account ID.

==================================================
STEP 13 — CLOSING JOURNAL
==================================================

Create the year-end closing journal through the Posting Engine.

The journal must contain:

Closing date

Financial year

Closing reference

Description

Source = Year-End Closing

Journal lines

Do not directly manipulate GL balances.

==================================================
STEP 14 — OPENING BALANCES
==================================================

Create opening balances for the next financial year.

Carry forward:

Cash

Bank

Accounts Receivable

Inventory

Fixed Assets

Other Assets

Accounts Payable

Loans

Tax Payables

Payroll Payables

Other Liabilities

Equity

Retained Earnings

Other permanent balance sheet accounts

==================================================
IMPORTANT
==================================================

Income Statement accounts do NOT simply carry their previous year's balances forward.

They start from zero for the new financial year.

Balance Sheet accounts carry forward their closing balances.

==================================================
STEP 15 — OPENING BALANCE VERIFICATION
==================================================

New Year Opening Balance must equal:

Previous Year Closing Balance

for all permanent accounts.

Verify:

Opening Debit = Closing Debit

Opening Credit = Closing Credit

Total Opening Debits = Total Opening Credits

==================================================
STEP 16 — YEAR-END REOPENING
==================================================

If the organization allows reopening:

Require:

Administrator permission

Reason

Approval

Audit log

Reversal/adjustment mechanism where necessary

Never delete the closing journal.

If reopening requires correction, preserve the original history.

==================================================
STEP 17 — MULTI-YEAR REPORTING
==================================================

Support:

Current Year

Previous Year

Multiple Year Comparison

Opening Balance

Closing Balance

Year-over-Year Variance

==================================================
STEP 18 — CLOSING DASHBOARD
==================================================

Show:

Current Financial Year

Current Period

Periods Closed

Periods Remaining

Closing Checklist Progress

Unreconciled Modules

Pending Adjustments

Current Net Profit

Current Net Loss

Closing Status

==================================================
STEP 19 — CLOSING REPORTS
==================================================

Build:

1. Period Closing Report

2. Year-End Closing Report

3. Closing Checklist Report

4. Opening Balance Report

5. Closing Journal Report

6. Unreconciled Accounts Report

7. Year-over-Year Financial Report

==================================================
STEP 20 — CLOSING AUDIT TRAIL
==================================================

Record:

Period opened

Period closed

Period reopened

Adjustment created

Checklist completed

Year-end validation started

Year-end validation completed

Year closed

Opening balances generated

Year reopened

Record:

User

Timestamp

Reason

IP if existing audit architecture supports it

==================================================
STEP 21 — RBAC
==================================================

Permissions:

period.view

period.create

period.close

period.reopen

year.close

year.reopen

closing.adjust

closing.approve

closing.report

==================================================
STEP 22 — SECURITY
==================================================

Only authorized finance users can:

Close periods

Reopen periods

Create closing adjustments

Close financial years

Reopen financial years

==================================================
STEP 23 — INTEGRATION
==================================================

Integrate with:

General Ledger

Posting Engine

Chart of Accounts

AR

AP

Banking

Tax

Fixed Assets

Payroll

Expenses

Budget

Financial Reports

Audit Logs

RBAC

==================================================
STEP 24 — CLOSING WORKFLOW
==================================================

Build:

START CLOSING

↓

Run Validation

↓

Reconciliation

↓

Closing Checklist

↓

Adjustments

↓

Final Trial Balance

↓

Final P&L

↓

Final Balance Sheet

↓

Management Approval

↓

Close Periods

↓

Year-End Closing

↓

Close Temporary Accounts

↓

Retained Earnings

↓

Generate Opening Balances

↓

Create New Financial Year

==================================================
STEP 25 — ERROR HANDLING
==================================================

If any critical validation fails:

STOP closing.

Display:

Problem

Module

Affected account/transaction

Reason

Required action

Do not partially close the year.

==================================================
STEP 26 — IDEMPOTENCY
==================================================

The year-end closing operation MUST NOT execute twice.

If the year is already closed:

Reject duplicate closing.

If opening balances already exist:

Do not create duplicate opening balances.

==================================================
STEP 27 — UI
==================================================

Create professional ERP screens:

Financial Years

Accounting Periods

Period Close

Year-End Close

Closing Checklist

Reconciliation Status

Closing Adjustments

Opening Balances

Closing History

Closing Reports

Use Tailwind CSS.

==================================================
OUTPUT
==================================================

Generate:

1. Financial Year Management

2. Accounting Period Management

3. Period Locking

4. Period Reopening

5. Closing Checklist

6. Reconciliation Validation

7. Closing Adjustments

8. Year-End Closing Engine

9. Retained Earnings Processing

10. Opening Balance Engine

11. Closing Reports

12. Closing Dashboard

13. Audit Logging

14. RBAC

15. Validation

16. Tailwind UI

==================================================
CRITICAL RULE
==================================================

Accounting history is immutable.

Never solve a closing problem by changing historical transactions.

Use:

Reversal

Adjustment

Corrective Journal

==================================================
DO NOT BUILD
==================================================

❌ Financial Consolidation

❌ Intercompany Accounting

❌ Group Reporting

❌ Manufacturing Costing

❌ Government Tax Filing

❌ Audit Management

These belong to later phases.