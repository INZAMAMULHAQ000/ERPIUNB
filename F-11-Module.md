You are a Senior ERP Finance Architect, Chartered Accountant (CA), Financial Reporting Specialist, and Senior Core PHP Developer.

Finance phases F1 through F10 have been completed and verified:

F1 — Finance Foundation
F2 — General Ledger & Posting Engine
F3 — Banking & Cash Management
F4 — Accounts Receivable
F5 — Accounts Payable
F6 — Expense Management
F7 — Budget Management
F8 — Tax Management
F9 — Fixed Assets
F10 — Payroll Finance Integration

Now build:

==================================================
PHASE F11 — FINANCIAL REPORTING & FINANCIAL STATEMENTS
==================================================

Use PDO and the existing ERP architecture.

==================================================
CORE PRINCIPLE
==================================================

Reports MUST NOT maintain independent financial balances.

Reports must derive their data from:

Journal Entries
Journal Entry Lines
General Ledger
Chart of Accounts
Accounting Periods
AR
AP
Banking
Tax
Fixed Assets
Payroll
Budgets

The General Ledger is the accounting source of truth.

==================================================
OBJECTIVE
==================================================

Build a complete enterprise Financial Reporting system.

Support:

- Trial Balance
- Profit & Loss
- Balance Sheet
- Cash Flow Statement
- General Ledger
- Account Ledger
- AR reports
- AP reports
- Tax reports
- Fixed Asset reports
- Expense reports
- Budget vs Actual
- Financial dashboards
- Management reports

==================================================
MODULES
==================================================

1. Financial Dashboard

2. Trial Balance

3. Profit & Loss

4. Balance Sheet

5. Cash Flow Statement

6. General Ledger

7. Account Ledger

8. AR Reports

9. AP Reports

10. Tax Reports

11. Fixed Asset Reports

12. Expense Reports

13. Budget Reports

14. Payroll Reports

15. Financial Ratios

16. Report Export

==================================================
STEP 1 — FOLDER STRUCTURE
==================================================

Create:

modules/

finance/

reports/

    dashboard/

    trial_balance/

    profit_loss/

    balance_sheet/

    cash_flow/

    general_ledger/

    account_ledger/

    ar/

    ap/

    tax/

    assets/

    expenses/

    budget/

    payroll/

    ratios/

    exports/

==================================================
STEP 2 — REPORT ENGINE
==================================================

Create a centralized reporting service.

Example:

FinancialReportService

It should support:

generateTrialBalance()

generateProfitLoss()

generateBalanceSheet()

generateCashFlow()

generateGeneralLedger()

generateAccountLedger()

Do NOT place financial calculation logic inside individual PHP pages.

==================================================
STEP 3 — REPORT PARAMETERS
==================================================

All reports should support:

Company

Financial Year

Accounting Period

Start Date

End Date

Currency

Account

Account Group

Department

Project

Cost Center

Branch

==================================================
STEP 4 — TRIAL BALANCE
==================================================

Build Trial Balance.

Columns:

Account Code

Account Name

Opening Debit

Opening Credit

Period Debit

Period Credit

Closing Debit

Closing Credit

==================================================
VALIDATION
==================================================

Total Debit

MUST EQUAL

Total Credit

If not:

Display a critical accounting integrity warning.

==================================================
STEP 5 — PROFIT & LOSS
==================================================

Build:

Revenue

Cost of Sales

Gross Profit

Operating Expenses

Operating Profit

Other Income

Other Expenses

Profit Before Tax

Tax Expense

Net Profit

==================================================
STEP 6 — PROFIT & LOSS FILTERS
==================================================

Support:

Monthly

Quarterly

Yearly

Custom Date Range

Department

Project

Cost Center

Branch

==================================================
STEP 7 — BALANCE SHEET
==================================================

Build:

ASSETS

Current Assets

Cash

Bank

Accounts Receivable

Inventory

Other Current Assets

Non-Current Assets

Fixed Assets

Accumulated Depreciation

Other Non-Current Assets

TOTAL ASSETS

--------------------------------------------------

LIABILITIES

Current Liabilities

Accounts Payable

Payroll Payable

Tax Payable

Other Current Liabilities

Non-Current Liabilities

Loans

Other Long-Term Liabilities

TOTAL LIABILITIES

--------------------------------------------------

EQUITY

Share Capital

Retained Earnings

Current Year Profit/Loss

Other Equity

TOTAL EQUITY

--------------------------------------------------

VALIDATION

Total Assets

MUST EQUAL

Total Liabilities + Equity

If not:

Display accounting integrity warning.

==================================================
STEP 8 — GENERAL LEDGER
==================================================

Build a complete General Ledger viewer.

Columns:

Date

Journal Number

Reference

Description

Debit

Credit

Running Balance

==================================================
FILTERS

Account

Date Range

Journal Type

Reference

Department

Project

User

==================================================
STEP 9 — ACCOUNT LEDGER
==================================================

Allow user to select an account.

Display:

Opening Balance

Transactions

Debit

Credit

Running Balance

Closing Balance

==================================================
STEP 10 — CASH FLOW STATEMENT
==================================================

Build:

Operating Activities

Investing Activities

Financing Activities

Net Cash Flow

Opening Cash

Closing Cash

==================================================
IMPORTANT
==================================================

Design the system so Cash Flow classification is configurable.

Do NOT simply assume every debit/credit account belongs to one fixed cash-flow category.

Allow configuration such as:

Operating

Investing

Financing

Non-Cash

==================================================
STEP 11 — AR REPORTS
==================================================

Build:

AR Aging

Customer Balance

Customer Statement

Outstanding Invoices

Overdue Invoices

Collection Summary

Days Sales Outstanding

==================================================
STEP 12 — AP REPORTS
==================================================

Build:

AP Aging

Vendor Balance

Vendor Statement

Outstanding Bills

Overdue Bills

Payment Summary

Days Payable Outstanding

==================================================
STEP 13 — TAX REPORTS
==================================================

Integrate with F8.

Show:

Output Tax

Input Tax

Net Tax Payable

Taxable Sales

Taxable Purchases

Exempt Transactions

Zero-Rated Transactions

Tax Adjustments

Tax Period Summary

==================================================
STEP 14 — FIXED ASSET REPORTS
==================================================

Integrate with F9.

Show:

Asset Cost

Accumulated Depreciation

Net Book Value

Depreciation Expense

Asset Additions

Asset Disposals

Gain/Loss

==================================================
STEP 15 — EXPENSE REPORTS
==================================================

Show:

Expense by Category

Expense by Department

Expense by Project

Expense by Employee

Monthly Expense

Yearly Expense

Top Expense Categories

==================================================
STEP 16 — BUDGET REPORTS
==================================================

Integrate with F7.

Show:

Budget

Actual

Variance

Variance %

Budget Utilization

Department Variance

Project Variance

Account Variance

==================================================
STEP 17 — PAYROLL REPORTS
==================================================

Integrate with F10.

Show:

Payroll Cost

Salary Expense

Employee Payable

Employer Contributions

Payroll Payments

Department Payroll

Monthly Payroll

==================================================
STEP 18 — FINANCIAL RATIOS
==================================================

Build configurable financial ratios.

Examples:

Current Ratio

Quick Ratio

Gross Profit Margin

Net Profit Margin

Operating Margin

Debt-to-Equity

Return on Assets

Return on Equity

Receivables Days

Payables Days

==================================================
IMPORTANT
==================================================

Ratio formulas should be implemented in a centralized service.

Do not hard-code formulas inside dashboard pages.

==================================================
STEP 19 — FINANCIAL DASHBOARD
==================================================

Create executive dashboard.

Cards:

Revenue

Expenses

Gross Profit

Net Profit

Cash & Bank

Accounts Receivable

Accounts Payable

Tax Payable

Total Assets

Total Liabilities

Equity

==================================================
CHARTS

Revenue Trend

Expense Trend

Profit Trend

Cash Flow Trend

AR vs AP

Budget vs Actual

Expense Distribution

==================================================
STEP 20 — COMPARATIVE REPORTING
==================================================

Support:

Current Period

Previous Period

Current Year

Previous Year

Budget

Actual

Variance

Example:

Revenue

2026 Actual

2025 Actual

Variance

Variance %

==================================================
STEP 21 — MULTI-CURRENCY
==================================================

If the existing ERP supports multiple currencies:

Reports must support:

Transaction Currency

Base Currency

Exchange Rate

Foreign Currency Gain/Loss

Do not mix currencies in totals.

==================================================
STEP 22 — PERIOD CONTROL
==================================================

Reports must respect:

Financial Year

Accounting Period

Closed Period

Opening Balance

Closing Balance

Never modify accounting data while generating reports.

==================================================
STEP 23 — REPORT EXPORT
==================================================

Support:

PDF

Excel

CSV

Print

Use the existing project file-generation architecture.

Reports must preserve:

Company Name

Report Name

Period

Generated Date

Currency

Filters

==================================================
STEP 24 — REPORT SECURITY
==================================================

Respect existing RBAC.

Permissions:

report.view

report.export

report.financial

report.management

report.audit

Users must only see data permitted by their role.

==================================================
STEP 25 — AUDIT
==================================================

Record:

Report generated

Report exported

Report parameters

Generated by

Generated timestamp

==================================================
STEP 26 — REPORT RECONCILIATION
==================================================

Build an accounting integrity checker.

Check:

Trial Balance

P&L

Balance Sheet

AR

AP

Tax

Fixed Assets

Payroll

Bank

Budget

==================================================
INTEGRITY CHECKS
==================================================

Trial Balance:

Debit = Credit

Balance Sheet:

Assets = Liabilities + Equity

AR:

Customer balances reconcile with AR control account

AP:

Vendor balances reconcile with AP control account

Tax:

Tax subledger reconciles with Tax GL accounts

Fixed Assets:

Asset register reconciles with Fixed Asset GL

Payroll:

Payroll payable reconciles with Payroll GL

Bank:

Bank ledger reconciles with bank records

==================================================
STEP 27 — REPORT UI
==================================================

Build professional ERP report pages.

Every report should have:

Report Header

Filter Panel

Date Range

Account Selector

Department Selector

Project Selector

Generate Button

Export Button

Print Button

Results Table

Totals

Pagination

==================================================
STEP 28 — DRILL-DOWN
==================================================

IMPORTANT.

Reports must support drill-down.

Example:

Profit & Loss

↓

Operating Expenses

↓

Travel Expense

↓

Account Ledger

↓

Journal Entry

↓

Source Transaction

Example:

AR Aging

↓

Customer

↓

Invoice

↓

Invoice Items

↓

Journal Entry

==================================================
OUTPUT
==================================================

Generate:

1. Financial Report Engine

2. Trial Balance

3. Profit & Loss

4. Balance Sheet

5. Cash Flow Statement

6. General Ledger

7. Account Ledger

8. AR Reports

9. AP Reports

10. Tax Reports

11. Fixed Asset Reports

12. Expense Reports

13. Budget Reports

14. Payroll Reports

15. Financial Ratios

16. Executive Dashboard

17. Comparative Reports

18. Export Engine

19. Drill-down System

20. Integrity Checker

21. RBAC

22. Audit Logging

23. Tailwind UI

==================================================
CRITICAL RULE
==================================================

NEVER create duplicate financial balances for reporting.

The reporting hierarchy must be:

Source Transaction

↓

Journal Entry

↓

General Ledger

↓

Financial Report

Reports are READ-ONLY.

==================================================
DO NOT BUILD
==================================================

❌ Year-End Closing

❌ Financial Consolidation

❌ Intercompany Accounting

❌ Group Consolidation

❌ Audit Management

❌ Government Tax Filing

These will be later phases.