Build **Phase I2 — Inventory Costing & Valuation** for the existing ERP.

### Fixed technology
- Core PHP
- MySQL
- HTML + Tailwind CSS
- Vanilla JavaScript only
- PDO/prepared statements
- Separate PHP screen per page
- No React/Vue/Angular/Next.js
- No SPA
- No ORM/framework

### Important
Keep this phase compact. **Do not redesign Inventory I1.**
Reuse the existing Inventory Ledger, Stock Balance, Item Master, Warehouse, Company, Finance Posting Engine, RBAC and Audit infrastructure.

---

# 1. Inventory Costing

Implement the ERP's core inventory costing using the costing method already selected/configured by the system.

If no costing method currently exists, support **Weighted Average Cost** as the initial method.

Calculate:

- Quantity
- Unit cost
- Inventory value
- Average cost

All calculations must be server-side.

Do not trust browser-submitted totals.

---

# 2. Purchase Receipt Cost

When a purchase receipt is posted:

- Capture the actual inventory cost from the receipt/related purchase information.
- Update inventory valuation.
- Recalculate weighted-average cost where applicable.
- Do not create duplicate inventory movements.

Use existing Procurement and Finance data instead of creating duplicate purchase-cost records.

---

# 3. Inventory Issue Cost

When inventory is issued:

- Determine the correct inventory cost using the configured costing method.
- Record the cost against the inventory movement.
- Reduce inventory value correctly.

Do not allow an issue to silently create an incorrect negative valuation.

---

# 4. Stock Adjustment Valuation

For inventory adjustments:

- Stock increase must receive an appropriate unit cost.
- Stock decrease must use the current inventory cost.
- Record the resulting valuation in the inventory ledger.

Require a reason for material valuation adjustments.

---

# 5. Stock Transfer Valuation

Transfers between warehouses/locations must:

- Preserve inventory value.
- Move the same valuation from source to destination.
- Not artificially create profit or loss.

The transfer must remain atomic.

---

# 6. Inventory Valuation Report

Create one simple report showing:

- Item
- Warehouse
- Quantity
- Unit cost
- Inventory value

Filters:

- Company
- Warehouse
- Item
- As-of date

Add a total inventory value.

---

# 7. Cost Audit

Create a basic inventory valuation history showing:

- Movement
- Quantity
- Cost before movement
- Cost of movement
- Cost after movement
- Inventory value before
- Inventory value after

This is for audit/debugging and financial reconciliation.

---

# 8. Finance Integration

Use the existing centralized Finance Posting Engine.

Do NOT directly modify GL balances.

Where inventory accounting is configured, ensure inventory value changes generate the appropriate accounting through the existing posting architecture.

Do not hardcode account IDs.

---

# 9. Data Integrity

Prevent:

- Duplicate costing for the same movement
- Editing posted costing transactions
- Deleting posted inventory transactions
- Cross-company valuation access
- Browser-side manipulation of costs

Use database transactions for posting.

---

# 10. Testing

Test with:

### Purchase 1
Buy 100 units at AED 10.

Expected:
- Quantity = 100
- Inventory value = AED 1,000
- Average cost = AED 10

### Purchase 2
Buy 100 units at AED 20.

Expected:
- Quantity = 200
- Inventory value = AED 3,000
- Weighted average cost = AED 15

### Issue
Issue 50 units.

Expected:
- Quantity = 150
- Issue cost = AED 15 per unit
- Remaining inventory value = AED 2,250

### Transfer
Transfer 20 units between warehouses.

Verify:
- Total company inventory value remains unchanged.
- Source and destination valuations are correct.

### Adjustment
Increase stock by 10 units.

Verify the valuation is calculated correctly.

### Security
Attempt to modify the cost through POST/request manipulation.
Verify the server rejects or recalculates it.

### Accounting
Verify any inventory accounting entries use the existing Finance Posting Engine.

---

## Completion condition

I2 is complete when:

**Purchase → Costing → Inventory Valuation → Issue → Transfer → Adjustment → Finance Integration**

works correctly.

Do NOT build:

- FIFO/LIFO engine unless already required by the existing architecture
- Advanced landed-cost module
- Cost simulation
- Inventory forecasting
- AI costing
- complex costing analytics
- manufacturing costing

Keep this phase focused and small.