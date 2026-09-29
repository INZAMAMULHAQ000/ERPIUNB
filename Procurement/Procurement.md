You are a Senior ERP Architect, Procurement Management Specialist, Supply Chain Consultant, and Senior Core PHP Developer.

The Finance module F1–F13 has been completed and verified.

Now begin:

==================================================
PHASE P1 — PROCUREMENT FOUNDATION & PURCHASE REQUISITION
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

Every screen must be a separate PHP page following the existing ERP architecture.

Use PDO.

==================================================
IMPORTANT ARCHITECTURAL RULE
==================================================

DO NOT build Vendor Management again.

Use the existing Vendor/Supplier master.

DO NOT build Finance again.

Use the existing:

- Chart of Accounts
- AP
- Posting Engine
- Company
- Department
- Project
- Cost Center
- Users
- RBAC
- Approval
- Audit Log

Procurement must integrate with those existing modules.

==================================================
OBJECTIVE
==================================================

Build the foundation of the Procurement module.

The first workflow is:

Purchase Requisition

↓

Approval

↓

Approved Requisition

↓

Ready for RFQ

Future phases will continue:

RFQ

↓

Vendor Quotations

↓

Quotation Comparison

↓

Purchase Order

↓

Goods Receipt

↓

Vendor Bill

↓

Payment

==================================================
MODULE STRUCTURE
==================================================

Create:

modules/

procurement/

    dashboard/

    requisitions/

    rfq/

    quotations/

    comparison/

    purchase_orders/

    receipts/

    reports/

    settings/

==================================================
STEP 1 — PROCUREMENT SETTINGS
==================================================

Create procurement settings.

Support:

Purchase Requisition Number Prefix

RFQ Number Prefix

Quotation Number Prefix

Purchase Order Number Prefix

Goods Receipt Number Prefix

Default Requester

Default Currency

Default Tax

Approval Configuration

Numbering Configuration

==================================================
STEP 2 — PURCHASE REQUISITION
==================================================

Create Purchase Requisition.

Header:

PR Number

Request Date

Requester

Department

Company

Branch

Project

Cost Center

Required Date

Priority

Purpose

Description

Status

==================================================
PRIORITIES
==================================================

Low

Normal

High

Urgent

==================================================
STEP 3 — PR ITEMS
==================================================

Each requisition can contain multiple items.

Fields:

Item

Description

Quantity

Unit

Estimated Unit Price

Estimated Total

Required Date

Preferred Vendor

Department

Project

Cost Center

Tax

Notes

==================================================
IMPORTANT
==================================================

Do not assume every requisition item is inventory.

Support:

Stock Item

Non-Stock Item

Service

Asset

Expense

==================================================
STEP 4 — PR NUMBERING
==================================================

Generate unique numbers.

Example:

PR-2026-000001

PR-2026-000002

Numbers must be generated server-side.

Prevent duplicates under concurrent requests.

==================================================
STEP 5 — PR CALCULATION
==================================================

For every line:

Quantity × Estimated Unit Price

=

Estimated Line Total

Then:

Subtotal

+

Tax

-

Discount

=

Estimated Total

All monetary calculations must happen server-side.

Never trust browser-calculated totals.

==================================================
STEP 6 — PR STATUS
==================================================

Draft

Submitted

Pending Approval

Approved

Rejected

Cancelled

Closed

==================================================
STEP 7 — PR WORKFLOW
==================================================

Draft

↓

Submit

↓

Pending Approval

↓

Approval

↓

Approved

or

Rejected

==================================================
STEP 8 — APPROVAL
==================================================

Integrate with the existing approval/RBAC architecture.

Approval should consider:

Company

Department

Amount

Requester

Purchase Type

==================================================
IMPORTANT

DO NOT hard-code:

"If amount > X then manager approval."

Use configurable approval rules if the existing ERP approval engine supports them.

==================================================
STEP 9 — APPROVAL HISTORY
==================================================

Record:

Approver

Action

Date

Time

Comments

Previous Status

New Status

==================================================
STEP 10 — VALIDATION
==================================================

Prevent submission when:

Requester missing

Company missing

Department missing

Required date missing

No items

Invalid quantity

Invalid price

Invalid tax

Invalid project

Invalid cost center

Inactive vendor selected

==================================================
STEP 11 — EDIT RULES
==================================================

Draft:

Fully editable.

Submitted:

Not freely editable.

Pending Approval:

Locked except authorized correction/rejection workflow.

Approved:

Locked.

Rejected:

Can be revised and resubmitted.

Cancelled:

Read-only.

==================================================
STEP 12 — CANCELLATION
==================================================

Allow authorized cancellation.

Require:

Reason

User

Timestamp

Audit entry

==================================================
STEP 13 — PROCUREMENT DASHBOARD
==================================================

Create dashboard cards:

Total PRs

Draft PRs

Pending Approval

Approved PRs

Rejected PRs

Urgent PRs

PR Value This Month

PRs Awaiting Procurement

==================================================
STEP 14 — PR LIST
==================================================

Build searchable list.

Columns:

PR Number

Date

Requester

Department

Company

Total

Priority

Required Date

Status

Actions

==================================================
FILTERS
==================================================

PR Number

Date Range

Requester

Department

Company

Project

Cost Center

Priority

Status

==================================================
STEP 15 — PR DETAIL
==================================================

Create professional detail page.

Sections:

Header

Requester Information

Requirement Information

Items

Totals

Approval History

Attachments

Audit History

==================================================
STEP 16 — ATTACHMENTS
==================================================

Allow attachments such as:

Specification

Technical Requirement

Quotation Request Document

Supporting Document

Approval Document

Use the existing document/attachment architecture if available.

Do not create duplicate file-management infrastructure.

==================================================
STEP 17 — AUDIT LOGGING
==================================================

Record:

PR Created

PR Edited

PR Submitted

PR Approved

PR Rejected

PR Resubmitted

PR Cancelled

PR Attachment Added

==================================================
STEP 18 — RBAC
==================================================

Create permissions:

procurement.view

procurement.pr.create

procurement.pr.edit

procurement.pr.submit

procurement.pr.approve

procurement.pr.reject

procurement.pr.cancel

procurement.pr.report

==================================================
STEP 19 — PROCUREMENT REPORTS
==================================================

Build initial reports:

1. Purchase Requisition Register

2. Pending Approval Report

3. Approved Requisition Report

4. Rejected Requisition Report

5. PR Value by Department

6. PR Value by Project

7. PR Value by Cost Center

==================================================
STEP 20 — FUTURE INTEGRATION
==================================================

Design PR so an approved requisition can later become:

PR

↓

RFQ

without re-entering all information.

Approved PR items must be selectable when creating RFQs.

==================================================
STEP 21 — DATABASE
==================================================

Create appropriate tables for:

purchase_requisitions

purchase_requisition_items

purchase_requisition_approvals

purchase_requisition_attachments

Use proper:

Primary Keys

Foreign Keys

Indexes

Unique Constraints

Timestamps

Company isolation where applicable.

Follow the existing database naming conventions.

==================================================
STEP 22 — SECURITY
==================================================

Use:

PDO prepared statements

Server-side validation

CSRF protection

RBAC

Company-level access control

Input sanitization

Output escaping

Do not trust:

POST values

GET values

Hidden fields

JavaScript calculations

==================================================
STEP 23 — UI
==================================================

Use professional ERP-style Tailwind UI.

Create separate PHP screens for:

procurement dashboard

PR list

PR create

PR edit

PR view

PR approval

PR reports

No SPA.

No frontend framework.

==================================================
STEP 24 — INTEGRATION CHECK
==================================================

Before completing the phase verify:

Existing Users work

Existing Departments work

Existing Companies work

Existing Projects work

Existing Cost Centers work

Existing Vendors work

Existing RBAC works

Existing Approval system works

Existing Audit Log works

==================================================
OUTPUT
==================================================

Build:

1. Procurement Settings

2. Purchase Requisition Database

3. PR Create

4. PR Edit

5. PR View

6. PR List

7. PR Item Management

8. PR Calculations

9. PR Approval Workflow

10. PR Cancellation

11. PR Attachments

12. PR Dashboard

13. PR Reports

14. RBAC

15. Audit Logging

16. Validation

17. Tailwind UI

==================================================
DO NOT BUILD YET
==================================================

❌ RFQ

❌ Vendor Quotation

❌ Quotation Comparison

❌ Purchase Order

❌ Goods Receipt

❌ Inventory

❌ Vendor Bill

❌ Payment

These will be implemented in subsequent procurement phases.

==================================================
FINAL REQUIREMENT
==================================================

Do not modify existing Finance functionality unless required for integration.

Do not duplicate existing masters.

Build this phase as a clean foundation for the complete procurement lifecycle.



-------

Testing

Test P1

After the agent finishes, test in this order:

1. Create PR

Create:

Department: IT
Priority: High
Required Date: future date
2–3 items
Quantities and estimated prices

Verify totals.

2. Server-side calculation

Manipulate the browser's calculated total using DevTools and submit.

The server must ignore the manipulated total and recalculate it.

3. Submit

Draft → Submit.

Verify:

Pending Approval

4. Approval

Approve it.

Verify:

Approved

and the approval history contains the approver, timestamp and action.

5. Rejection

Create another PR and reject it.

Verify:

Rejected

Then revise and resubmit it.

6. Permissions

Use a user without approval permission.

They must not be able to approve the PR even if they manually access the PHP URL.

7. Company isolation

If multiple companies exist, verify a user cannot see PRs belonging to an unauthorized company.

8. Cancellation

Cancel an approved/eligible PR and verify:

Reason required
Audit entry created
Status changed correctly
Appropriate downstream actions are prevented
9. Database integrity

Delete/disable a vendor, department, project, etc. and verify existing PR records aren't accidentally corrupted.

10. Most important

Open an approved PR and confirm that every item is available to the next procurement phase without retyping it.

Once P1 is verified, send me the result and we'll move to P2 — RFQ & Vendor Quotation Management.