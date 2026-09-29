Build **Phase I3 — Inventory Controls, Reservations & Reconciliation** for the existing ERP.

### Fixed stack
- Core PHP
- MySQL
- HTML
- Tailwind CSS
- Vanilla JS only
- PDO/prepared statements
- Separate PHP screen per page
- No React/Vue/Angular/Next.js
- No SPA
- No ORM/framework

### Important
This is the **FINAL small Inventory Core phase**.

Do not redesign I1/I2.
Reuse the existing Inventory Ledger, Stock Balance, Costing, Procurement, Sales data if already available, RBAC, Audit and Finance infrastructure.

---

# 1. Stock Reservation

Add a basic reservation mechanism.

A reservation should contain:

- Company
- Item
- Warehouse/location
- Quantity
- Reference type
- Reference ID
- Status
- Created by/date
- Released by/date

Statuses:

- Reserved
- Released
- Consumed
- Cancelled

Reserved quantity must not be counted as freely available stock.

Use:

**Available = On-hand − Reserved**

Do not allow available stock to become negative when negative stock is disabled.

---

# 2. Reservation Lifecycle

Support:

**Create → Reserve → Consume/Release → Complete**

If the related transaction is cancelled, its reservation must be released appropriately.

Do not allow users to manually manipulate reservation quantities after the related transaction has been finalized.

Use database transactions.

---

# 3. Stock Reconciliation

Create one simple **Inventory Reconciliation** screen.

Allow an authorized user to compare:

- Inventory ledger quantity
- Current stock balance
- Reserved quantity
- Available quantity
- Inventory valuation

Flag inconsistencies.

Do NOT silently repair discrepancies.

Provide a controlled reconciliation/adjustment action that creates a proper inventory transaction and audit record.

---

# 4. Inventory Dashboard

Keep the dashboard minimal.

Show:

- Total items with stock
- Total on-hand quantity
- Total reserved quantity
- Total available quantity
- Total inventory value
- Low/out-of-stock items if a reorder/minimum level already exists

Do not build advanced analytics.

---

# 5. Inventory Audit

Provide basic searchable history for:

- Stock movements
- Adjustments
- Transfers
- Reservations
- Cost changes
- Reconciliation actions

Filters:

- Company
- Item
- Warehouse
- Date
- User
- Transaction type

Reuse the existing audit infrastructure where possible.

---

# 6. Security

Verify:

- Company isolation
- RBAC
- Server-side quantity validation
- CSRF protection
- No direct stock manipulation
- No editing/deleting finalized movements
- No unauthorized reconciliation
- No unauthorized reservation release

---

# 7. Testing

### Reservation
On-hand = 100.

Reserve 30.

Expected:

- On-hand = 100
- Reserved = 30
- Available = 70

### Second reservation
Attempt to reserve 80 more.

Expected:

- Blocked if negative available stock is disabled.

### Release
Release 10.

Expected:

- Reserved = 20
- Available = 80

### Consume
Consume 20.

Expected:

- Reservation becomes consumed.
- Stock movement reflects the actual issue.
- No duplicate stock deduction.

### Cancellation
Cancel the related transaction.

Verify reservation is released/cancelled correctly.

### Reconciliation
Create a controlled discrepancy.

Verify:
- Discrepancy is detected.
- System does not silently change stock.
- Authorized reconciliation creates a proper inventory transaction.
- Audit record exists.

### Security
Attempt to manipulate reservation/stock quantities through POST requests.

Verify server-side validation prevents it.

---

# Completion condition

Inventory Core is complete when the following works reliably:

**Opening Stock → Purchase Receipt → Costing → Current Stock → Reservation → Issue/Consumption → Transfer → Adjustment → Reconciliation → Inventory Valuation**

Do NOT add further Inventory phases for:

- Advanced WMS
- Barcode/mobile warehouse
- Picking/wave management
- AI forecasting
- Demand planning
- MRP
- Advanced replenishment
- Complex batch genealogy
- Advanced serial management
- Warehouse automation

Those are optional future products, not required for ERP Core.

After I3 passes testing, **stop expanding Inventory Core and move to the next ERP module.**