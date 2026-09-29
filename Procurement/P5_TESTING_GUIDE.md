# P5 — Vendor Bill + 3-Way Matching
# Manual Testing Guide

## Prerequisites
- P1 (Purchase Requisition), P2 (RFQ/Quotations), P3 (Purchase Orders), P4 (Goods/Service Receipts) are all completed
- You have test data: at least 1 approved PO with items, and 1 accepted Goods Receipt or Service Receipt linked to that PO
- Finance settings configured: `finance_ap_account_id` and `finance_expense_account_id` in `settings` table
- Tax settings configured: `input_tax_account_id` in tax settings
- At least one `Purchase Voucher` voucher type exists in `voucher_types`
- Active financial year exists in `financial_years`
- You are logged in as a user with `procurement.bills.*` permissions (Super Admin has all)

---

## STEP 1: Access the Vendor Bills Module

**URL:** `http://localhost/111/modules/procurement/bills/index.php`

**Expected:**
- Page loads without errors
- You see the "Vendor Bills" heading
- Filter form is visible (Search, Status, Match Status, Vendor, Company)
- "Create Vendor Bill" button is visible (if you have permission)
- Table shows existing bills (if any), or "No vendor bills found."
- Columns: Bill #, Vendor, Company, PO, Date, Total, Status, Match, Actions

**If blank or error:** Check PHP error log, verify `bootstrap.php` loads correctly, verify `BillService.php` is included.

---

## STEP 2: Create Vendor Bill from Purchase Order

1. Click **"Create Vendor Bill"**
2. In "Source Document" section:
   - Source Type: `Purchase Order`
   - Select Document: choose an approved PO
   - Click **"Load Source"**

**Expected:**
- Page reloads with PO data populated
- Bill Header: Vendor, Company, Currency, Payment Terms auto-filled from PO
- Bill Items table: all PO items loaded with:
  - Item name, PO Item ID (hidden)
  - Quantity = 0 (editable)
  - Unit Price = PO unit price (editable)
  - Discount = PO discount (editable)
  - Tax % = PO tax % (editable)
  - Line Total = calculated (readonly display)
- Subtotal, Discount, Tax, Grand Total calculated in browser

3. Enter a **Vendor Invoice Number** (e.g., `TEST-INV-001`)
4. Set Bill Date and Due Date
5. For each line, enter **Quantity** (e.g., partial billing: enter less than PO qty)
6. Verify totals update correctly
7. Click **"Create Bill"**

**Expected:**
- Redirected to View page
- Flash message: "Vendor bill VB-2026-XXXXXX created successfully."
- Bill status = `draft`
- Match status = `pending`
- Bill number format: `VB-2026-000001`

---

## STEP 3: Verify Bill Details

On the View page, verify:

**Header section:**
- Bill Number: VB-2026-XXXXXX
- Vendor Invoice Number: TEST-INV-001
- Vendor: matches PO vendor
- Company: matches PO company
- PO Reference: clickable link to PO
- Bill Date / Due Date: as entered
- Currency, Payment Terms: correct

**Items section:**
- All items listed with correct qty, price, discount, tax
- Line totals correct
- PO Item IDs linked

**Summary sidebar:**
- Subtotal = sum of (qty × price)
- Discount = sum of discounts
- Tax = calculated correctly
- Grand Total = subtotal - discount + tax

---

## STEP 4: Test 3-Way Match

1. From the Bill View page, click **"3-Way Match"** (or navigate to `match.php?id=XX`)
2. Click **"Run 3-Way Match"**

**Expected:**
- Redirected back to View page
- Flash message: "3-way match completed. Status: matched (Variance: X%)"
- Match Results section appears showing:
  - PO Amount, Receipt Amount, Bill Amount
  - Variance amount and percent
- Exception Details (if any) shown

**Test with mismatched data:**
1. Edit the bill (while in `draft` or `rejected` status)
2. Change a line quantity or price to create a variance > 5% (default tolerance)
3. Submit and run 3-way match again

**Expected:**
- Match status = `exception`
- Exceptions section appears with:
  - Exception type (quantity_mismatch, price_mismatch, etc.)
  - Severity badge
  - Reason text
  - Difference amount and percent
  - Links to Resolve / Override / Reject

---

## STEP 5: Test Exception Resolution

1. Navigate to the exception (from Exceptions section on View page)
2. Click **"Override"** (requires `procurement.bills.override` permission)

**Expected:**
- Exception resolution form appears
- Exception details shown (type, severity, reason, difference)
- Reason/Comments textarea required
- Override Reason textarea required (for override action)
- Submit

**After submission:**
- Bill match_status changes to `overridden`
- Exception status changes to `overridden`
- Approval history records the override

**Test Resolve:**
- Click "Resolve" on an exception
- Enter reason
- Submit
- Exception status = `resolved` (or handled per implementation)

---

## STEP 6: Submit and Approve Bill

1. From Bill View, click **"Submit"**
2. Confirm submission

**Expected:**
- Status changes to `pending_approval`
- Approval record created

3. Click **"Approve"**
4. Enter optional comments
5. Confirm approval

**Expected:**
- Status changes to `approved`
- Approval history updated

---

## STEP 7: Post to Finance (GL Integration)

1. From Bill View (status = `approved` or `matched`), click **"Post to Finance"**
2. Confirm posting

**Expected:**
- Bill status changes to `posted`
- `journal_entry_id` populated
- `posted_by` and `posted_at` populated
- Flash message includes voucher number (e.g., "Voucher #PV-2025-2026-000001")

**Verify in Finance:**
- Check `journal_entries` table: new entry with `voucher_no` like `PV-2025-2026-XXXXXX`
- Check `journal_entry_details`: 
  - DR expense accounts (one per bill item, net of tax)
  - DR input tax account (if tax > 0)
  - CR AP account for total amount
- Check `general_ledger`: running balances updated
- Check `bill_status_history`: "approved → posted" record

---

## STEP 8: Test Partial Billing

1. Create a bill for a PO with quantity LESS than the PO ordered quantity
2. Complete the bill (submit, approve, post)

**Expected:**
- Bill created successfully
- `billed_quantity` on each line = billed qty
- `remaining_billable_quantity` calculated

3. Create a SECOND bill for the same PO, billing the remaining quantity

**Expected:**
- Second bill created successfully
- No over-billing error (total billed = PO ordered qty)

4. Try to create a THIRD bill exceeding remaining quantity

**Expected:**
- Error: "billed qty exceeds PO ordered qty. Over-billing not allowed."

---

## STEP 9: Test Over-Billing Protection

1. In `procurement_settings`, set `allow_over_billing` = `0` (default)
2. Try to bill MORE than the PO ordered quantity

**Expected:**
- Error message preventing over-billing

3. In `procurement_settings`, set `allow_over_billing` = `1`
4. Try to bill MORE than the PO ordered quantity again

**Expected:**
- Bill created successfully (override allowed via setting)

---

## STEP 10: Test Duplicate Invoice Protection

1. Create a bill with Vendor Invoice Number `DUP-TEST-001`
2. Complete and post the bill
3. Try to create another bill for the same vendor/company with the SAME Vendor Invoice Number

**Expected:**
- Error: "Duplicate vendor invoice number 'DUP-TEST-001' found on bill #VB-..."

---

## STEP 11: Test Service Bill (No Inventory Receipt)

1. Create a PO with `item_type = 'service'`
2. Create a Service Receipt (SR) for that PO
3. Create a Vendor Bill from the Service Receipt (source_type = `sr`)

**Expected:**
- Bill created successfully
- Linked to SR via `procurement_sr_id`
- 3-way match works (PO → SR → Bill)
- No Goods Receipt required

---

## STEP 12: Test Bill Cancellation

1. Create a bill in `draft` status
2. Click Cancel
3. Enter cancellation reason
4. Confirm

**Expected:**
- Status changes to `cancelled`
- Cannot be edited or posted after cancellation
- Audit log records cancellation

**Try to cancel a posted bill:**
**Expected:**
- Error: "Cannot cancel in status: posted"

---

## STEP 13: Test Dashboard Integration

**URL:** `http://localhost/111/modules/procurement/dashboard/index.php`

**Expected:**
- Scroll to "Vendor Bills" section
- KPI cards visible:
  - Open Bills (draft + pending_approval)
  - Pending Approval
  - Matched
  - Exceptions
  - Posted
  - Open Value
- Quick Links: New Bill, Pending Approval, 3-Way Match, Exceptions, Outstanding

---

## STEP 14: Test Reports

**URL:** `http://localhost/111/modules/procurement/bills/reports/index.php`

**Test each report:**

### a) Outstanding Procurement
- Select "Outstanding Procurement"
- Click "Run Report"
- **Expected:** Table of all non-cancelled bills with outstanding amounts (total - paid)

### b) Unbilled Receipts
- Select "Unbilled Receipts"
- Click "Run Report"
- **Expected:** Goods receipts with accepted qty > billed qty

### c) Match Exceptions
- Select "Match Exceptions"
- Click "Run Report"
- **Expected:** Bills with match_status = 'exception' or 'overridden', showing variance details

### d) Bill Register
- Select "Bill Register"
- Click "Run Report"
- **Expected:** All procurement bills with vendor, company, date, PO, total, status

---

## STEP 15: Test Traceability

1. Create a full chain: PR → RFQ → Quotation → Award → PO → GR → Vendor Bill → Posted
2. From the Bill View page, verify:
   - PO Reference links to PO detail
   - Receipt Reference links to GR/SR detail
   - Journal Entry ID shown (after posting)

3. Navigate backwards:
   - From Bill → click PO link → verify PO shows bill reference
   - From PO → verify linked PR, RFQ, Quotation

4. Check audit trail:
   - `procurement_audit` table: bill_created, bill_submitted, bill_approved, match_executed, bill_posted
   - `audit_logs` table: corresponding global audit entries

---

## STEP 16: Test RBAC Permissions

Test with different user roles:

| Permission | Expected Access |
|------------|-----------------|
| `procurement.bills.view` | Can view bills list and details |
| `procurement.bills.create` | Can create new bills |
| `procurement.bills.edit` | Can edit draft/rejected bills |
| `procurement.bills.approve` | Can approve bills |
| `procurement.bills.match` | Can run 3-way match |
| `procurement.bills.override` | Can override exceptions |
| `procurement.bills.cancel` | Can cancel bills |
| `procurement.bills.report` | Can view reports |

---

## STEP 17: Verify Data Integrity

Run these SQL checks:

```sql
-- Check all P5 tables have data
SELECT 'vendor_bill_matches' AS tbl, COUNT(*) AS cnt FROM vendor_bill_matches
UNION ALL
SELECT 'vendor_bill_exceptions', COUNT(*) FROM vendor_bill_exceptions
UNION ALL
SELECT 'vendor_bill_approvals', COUNT(*) FROM vendor_bill_approvals
UNION ALL
SELECT 'vendor_bill_attachments', COUNT(*) FROM vendor_bill_attachments;

-- Check bills are correctly linked
SELECT b.id, b.bill_number, b.procurement_po_id, po.po_number, b.match_status, b.status
FROM vendor_bills b
LEFT JOIN purchase_orders po ON po.id = b.procurement_po_id
WHERE b.is_procurement_bill = 1;

-- Check match results
SELECT b.bill_number, m.match_status, m.variance_amount, m.variance_percent
FROM vendor_bills b
JOIN vendor_bill_matches m ON m.bill_id = b.id
WHERE b.is_procurement_bill = 1;

-- Check journal entries were created
SELECT b.bill_number, b.journal_entry_id, je.voucher_no, je.status
FROM vendor_bills b
LEFT JOIN journal_entries je ON je.id = b.journal_entry_id
WHERE b.is_procurement_bill = 1 AND b.status = 'posted';

-- Check GL impact
SELECT je.voucher_no, jd.account_id, coa.account_code, coa.account_name, jd.debit_amount, jd.credit_amount
FROM journal_entries je
JOIN journal_entry_details jd ON jd.journal_entry_id = je.id
JOIN chart_of_accounts coa ON coa.id = jd.account_id
WHERE je.voucher_no LIKE 'PV-%'
ORDER BY je.id, jd.id;
```

---

## STEP 18: Edge Cases to Test

1. **Bill with no PO link** — should fail 3-way match with "Bill is not linked to a Purchase Order"
2. **Bill with no items** — should fail with "Bill has no items to post"
3. **Posting with no AP account configured** — should throw exception
4. **Posting with no expense account configured** — should throw exception
5. **Re-open a matched bill** — edit should reset match_status to pending
6. **Multiple receipts for same PO** — 3-way match should aggregate correctly
7. **Tax calculation** — verify tax amounts match PO/GR tax

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| "Vendor Bill" menu not visible | Grant `procurement.bills.view` permission to your role |
| "Create Bill" button missing | Grant `procurement.bills.create` permission |
| 3-way match shows "not linked to PO" | Ensure bill was created from a PO (not standalone) |
| Posting fails with "AP account not configured" | Set `finance_ap_account_id` in `settings` table |
| Posting fails with "Purchase Voucher not found" | Create a voucher type named "Purchase Voucher" in Finance |
| Voucher number not generating | Ensure active financial year exists |
| "Duplicate invoice" error on new bill | Check if same vendor/invoice number already exists |
| Over-billing error | Either reduce qty, or set `allow_over_billing=1` in settings |

---

## Quick Access URLs

```
Vendor Bills List:     /modules/procurement/bills/index.php
Create Bill:          /modules/procurement/bills/create.php
Bill Detail:          /modules/procurement/bills/view.php?id=XX
3-Way Match:          /modules/procurement/bills/match.php?id=XX
Approve Bill:         /modules/procurement/bills/approve.php?id=XX
Reports:              /modules/procurement/bills/reports/index.php
Procurement Dashboard: /modules/procurement/dashboard/index.php
```

---

## What Was NOT Built (per p5.md)

❌ Advanced supplier portal
❌ E-procurement marketplace
❌ Complex sourcing optimization
❌ Strategic sourcing analytics
❌ Contract lifecycle management
❌ Supplier risk management
❌ Spend AI
❌ Procurement forecasting
❌ Advanced auction system

These are future enterprise features, not part of P5 core.
