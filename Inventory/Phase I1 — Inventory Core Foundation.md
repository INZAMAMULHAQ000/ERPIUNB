Build **Phase I1 — Inventory Core Foundation** for the existing ERP.

### Fixed technology
- Core PHP
- MySQL
- HTML
- Tailwind CSS
- Vanilla JavaScript only where needed
- PDO/prepared statements
- Separate PHP file for each screen
- No React/Vue/Angular/Next.js
- No SPA
- No ORM/framework

### Important instruction
This is the **first and only foundation phase for Inventory Core**. Keep it practical and compact.

Reuse all existing ERP infrastructure:
- Companies
- Users/RBAC
- Items/products
- Units of measure
- Warehouses
- Locations if already implemented
- Vendors/customers
- Currencies
- Taxes
- Audit logs
- Attachments
- Existing approval infrastructure
- Existing accounting/posting engine
- Existing Procurement receipt integration

Do NOT create duplicate master systems.

---

# 1. Inventory Settings

Create only the settings required for core inventory:

- Negative stock allowed: Yes/No
- Default costing method if the ERP already supports costing configuration
- Default warehouse/location behavior
- Stock movement numbering
- Stock adjustment approval requirement

Do not build advanced inventory configuration.

---

# 2. Inventory Stock Ledger

Create the central inventory transaction/stock ledger.

Each inventory movement should record at minimum:

- Company
- Item
- Warehouse
- Location if applicable
- Batch/lot if already supported
- Serial number if already supported
- Movement type
- Quantity in
- Quantity out
- Unit cost where applicable
- Total value where applicable
- Reference type
- Reference ID
- Transaction date/time
- User
- Notes
- Status

Movement types should support the core cases:

- Purchase Receipt
- Sales Issue
- Inventory Adjustment In
- Inventory Adjustment Out
- Stock Transfer Out
- Stock Transfer In
- Opening Stock

Use a centralized inventory movement service/engine.

**Do not directly update stock quantities from random PHP pages.**

---

# 3. Current Stock

Create a reliable current-stock view based on the inventory ledger/current balance structure.

Show:

- Item
- Warehouse
- Location
- Available quantity
- Reserved quantity if reservation already exists
- On-hand quantity
- Average/unit cost if supported
- Stock value

Prevent negative stock when the setting disallows it.

All stock calculations must be server-side.

---

# 4. Stock Adjustment

Create a simple Stock Adjustment screen.

Features:

- Select warehouse
- Select item
- Enter counted/adjustment quantity
- Reason
- Notes
- Attachment
- Approval if configured
- Finalize adjustment

On finalization:

- Create inventory ledger transaction
- Update stock through the centralized inventory engine
- Create audit record

Do not allow users to edit/delete finalized inventory movements.

Corrections must use another adjustment/reversal transaction.

---

# 5. Stock Transfer

Create a basic warehouse/location transfer.

Flow:

**Draft → Approved if required → Posted**

Support:

- Source warehouse/location
- Destination warehouse/location
- Item
- Quantity
- Notes
- Transfer reference

Posting must create the appropriate inventory movements atomically.

If anything fails, the complete transaction must roll back.

Do not allow transfer quantity greater than available stock when negative stock is disabled.

---

# 6. Procurement Integration

Connect the existing Procurement Goods Receipt to Inventory.

When a Goods Receipt is finalized:

**PO → Goods Receipt → Inventory Movement**

Do not duplicate receiving functionality.

Use the existing Goods Receipt data and create inventory movements through the centralized inventory engine.

If the existing system already performs this correctly, verify it rather than rebuilding it.

---

# 7. Basic Inventory Reports

Create only these core reports:

1. Current Stock
2. Stock Movement Ledger
3. Stock Adjustment History
4. Stock Transfer History

Filters:

- Company
- Warehouse
- Item
- Date range
- Movement type

Keep reports simple.

---

# 8. Security & Data Integrity

Enforce:

- Company isolation
- RBAC
- Server-side validation
- Prepared statements
- CSRF protection
- Audit logging
- Transaction-safe posting
- No deletion of finalized inventory transactions

Users must never be able to manipulate stock by changing browser/request values.

---

# 9. Accounting Boundary

Do NOT build a new accounting system.

Inventory transactions must integrate with the existing Finance/Posting Engine only where the existing ERP architecture requires accounting entries.

Typical purchase-receipt accounting should use the existing configured inventory/GRNI accounts and posting mechanism.

Do not hardcode account IDs.

---

# 10. UI

Create separate PHP screens for:

- Inventory Dashboard
- Current Stock
- Stock Adjustments
- Stock Transfers
- Stock Movement Ledger
- Inventory Reports
- Inventory Settings

Use the existing ERP layout/navigation/components.

Keep the UI functional rather than decorative.

---

# 11. Testing

After implementation, test:

### Opening stock
Create opening stock for 100 units.
Verify current stock = 100.

### Purchase receipt
Receive 50 units through the existing Goods Receipt.
Verify inventory increases correctly.

### Adjustment
Adjust +10.
Verify stock becomes 160.

### Transfer
Transfer 20 from Warehouse A to Warehouse B.
Verify:
- A decreases by 20
- B increases by 20
- ledger contains both sides

### Negative stock
Disable negative stock.
Attempt to issue more stock than available.
Verify the transaction is blocked.

### Rollback
Force a failure during transfer.
Verify neither warehouse quantity changes.

### Security
Attempt to access another company's stock by changing company/item/warehouse IDs.
Verify access is denied.

### Finalization
Attempt to edit/delete a finalized movement.
Verify it is blocked.

### Procurement integration
Finalize a Goods Receipt.
Verify exactly one corresponding inventory movement is created and no duplicate movement occurs.

### Accounting
Where accounting is configured, verify inventory-related posting goes through the existing centralized Finance Posting Engine.

---

## Completion condition

Consider **I1 complete** when:

**Opening Stock → Purchase Receipt → Current Stock → Adjustment → Transfer → Inventory Ledger → Basic Reports**

all work correctly and securely.

Do NOT add:

- Advanced warehouse management
- Demand forecasting
- AI inventory optimization
- Barcode/mobile warehouse app
- Advanced picking/packing
- Wave management
- Complex reservations
- Advanced batch genealogy
- Advanced serial tracking
- Automated replenishment
- MRP
- Supply-chain planning
- Vendor portal

Those are outside this phase.

**Keep Inventory Core compact. Do not create unnecessary sub-modules or additional phases.**