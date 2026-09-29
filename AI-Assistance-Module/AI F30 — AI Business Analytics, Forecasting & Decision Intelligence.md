# AI F30 — AI Business Analytics, Forecasting & Decision Intelligence

Build **AI F30 — AI Business Analytics, Forecasting & Decision Intelligence** for the existing ERP.

## NON-NEGOTIABLE TECH STACK

- **Core PHP + MySQL only**
- **No frameworks**
- Reuse the existing ERP architecture and AI capabilities.
- Do not introduce Laravel, Symfony, CodeIgniter, React, Vue, Node.js, Python services, or another application framework.
- Integrate with F23 Observability, F24 Governance, F25 Agents, F26 Intelligence, F27 Documents, F29 Universal Copilot, and existing ERP reporting/data services.
- Do not create unrestricted SQL access for AI.

## OBJECTIVE

Give the ERP a reliable AI-powered business analysis layer that can answer questions such as:

> "How are sales performing this quarter?"

> "Why did procurement costs increase?"

> "What will our inventory look like next month?"

> "Which customers are becoming risky?"

> "What happens if we increase this product's reorder level?"

The system must clearly distinguish:

**Historical Fact → Calculation → Forecast → Scenario → Recommendation**

AI must never present a forecast or assumption as a confirmed business fact.

---

## 1. AI → ANALYTICS CENTER

Add:

**AI → Analytics**

Sections:

- Executive Overview
- KPIs
- Trends
- Forecasts
- Anomalies
- Comparisons
- Scenarios
- Recommendations
- Saved Analyses
- Scheduled Reports

---

## 2. NATURAL-LANGUAGE ANALYTICS

Allow users to ask:

- "Show revenue this year."
- "Compare this quarter with last quarter."
- "Which suppliers increased prices?"
- "What are our biggest expense categories?"
- "Why did margins decrease?"
- "Forecast inventory for the next 30 days."

Translate requests into **controlled analytical operations**.

The model must not generate unrestricted SQL.

---

## 3. CONTROLLED QUERY ENGINE

Create a server-side analytics query layer using:

- approved data sources
- registered metrics
- registered dimensions
- approved filters
- prepared parameters
- tenant filters
- row limits
- aggregation limits

Support operations such as:

- SUM
- COUNT
- AVG
- MIN
- MAX
- percentage change
- ratios
- period comparison
- grouping
- ranking

Reject arbitrary SQL.

---

## 4. METRIC REGISTRY

Create a centralized metric registry.

Examples:

- Revenue
- Gross profit
- Outstanding receivables
- Purchase spend
- Inventory value
- Stock turnover
- Overdue invoices
- Purchase-order cycle time
- Supplier delivery performance

Each metric should define:

- name
- description
- source
- calculation
- allowed dimensions
- permissions
- data classification
- currency handling
- aggregation behavior

This prevents different AI responses from calculating the same KPI differently.

---

## 5. DIMENSIONS

Support approved dimensions such as:

- date
- customer
- supplier
- product
- category
- department
- branch
- warehouse
- project
- salesperson

Respect existing ERP permissions.

---

## 6. ANALYTICAL RESULT

Standardize results as:

- metric
- value
- unit
- currency
- period
- filters
- comparison
- source
- calculation definition

Example:

**Revenue**

AED 4.2M

**+12.4% vs previous quarter**

Then allow the user to inspect how the number was calculated.

---

## 7. COMPARISON ENGINE

Support:

- current vs previous period
- year-over-year
- month-over-month
- quarter-over-quarter
- budget vs actual
- target vs actual
- supplier vs supplier
- branch vs branch
- department vs department

Prevent invalid comparisons where periods or definitions are incompatible.

---

## 8. TREND ANALYSIS

Detect meaningful trends using deterministic/statistical calculations.

Examples:

- increasing expenses
- declining sales
- deteriorating supplier performance
- increasing overdue receivables
- declining inventory turnover

AI may explain the trend after the underlying calculation has been established.

---

## 9. FORECASTING

Implement a provider-independent forecasting abstraction.

Support configurable forecasting methods appropriate to the available data.

At minimum support a simple baseline method such as:

- moving average
- weighted moving average
- historical trend

Allow future enhancement with more advanced statistical providers/models.

Do not force an LLM to perform forecasting mathematics.

---

## 10. FORECAST OUTPUT

Every forecast should include:

- forecast value
- forecast period
- methodology
- historical data range
- confidence/uncertainty indicator
- assumptions
- limitations

Example:

> Projected inventory: 1,240 units  
> Horizon: 30 days  
> Method: historical demand trend  
> Confidence: Medium

Never describe this as guaranteed.

---

## 11. SCENARIO ANALYSIS

Allow controlled what-if analysis.

Examples:

> "What happens if sales increase 10%?"

> "What happens if supplier prices increase 5%?"

> "What if we reduce reorder lead time by 20%?"

> "What happens if this customer pays 15 days late?"

Scenarios must operate on a **simulation**, not modify production data.

---

## 12. SCENARIO VARIABLES

Allow approved variables such as:

- sales growth
- purchase-price change
- quantity change
- payment delay
- lead time
- tax rate where appropriate
- reorder level
- demand rate

Administrators should control which variables are available.

---

## 13. SCENARIO SAFETY

Clearly label:

**Simulation — no ERP data has been changed.**

Do not allow scenario calculations to write to production tables.

---

## 14. AI BUSINESS EXPLANATION

After calculations, AI can explain:

- what changed
- why it changed
- major contributing factors
- possible implications
- recommended investigation

Use verified analytical results as grounding.

Do not allow unsupported causal claims.

Use wording such as:

> "The largest contributor appears to be..."

rather than:

> "This happened because..."

unless the data actually establishes causation.

---

## 15. DRILL-DOWN

From an analytical result allow:

**Drill down**

Example:

Revenue ↓ 8%

→ By region

→ By customer

→ By product

→ By month

→ Source transactions

All drill-downs must use authorized data sources.

---

## 16. AI CHARTS / VISUALIZATION DATA

Provide structured visualization output for the existing ERP UI.

Support:

- line
- bar
- stacked bar
- pie/donut where appropriate
- KPI card
- table
- trend indicator

The AI should return **structured chart data**, not executable frontend code.

Reuse existing chart components where available.

---

## 17. EXECUTIVE BRIEFING

Create an AI-generated executive analytics briefing:

### Business Snapshot

- Revenue
- Profit
- Cash/receivables indicators
- Procurement
- Inventory
- Operational issues

### Major Changes

- important increases/decreases

### Risks

- high-impact trends

### Opportunities

- meaningful improvement areas

The briefing must be based on authorized verified metrics.

---

## 18. ROLE-SPECIFIC ANALYTICS

Customize available analytics by role.

Examples:

### Finance

- receivables
- expenses
- profitability
- cash indicators

### Procurement

- spend
- supplier performance
- price trends
- PO cycle time

### Inventory

- stock levels
- demand
- turnover
- forecast

### Sales

- revenue
- customers
- conversion/performance metrics

### Management

- company-wide KPIs
- major risks
- strategic trends

Permissions remain authoritative.

---

## 19. F26 INTEGRATION

When analytics identify a meaningful anomaly or threshold breach, optionally create an F26 intelligence event.

Example:

Analytics detects:

> Supplier costs increased 18% over the last 3 months.

F26 can generate:

> Supplier cost increase detected.

The user can then investigate through F29.

---

## 20. F25 INTEGRATION

Allow analytical recommendations to become controlled F25 tasks.

Example:

> "Review the five suppliers with the largest price increases and prepare a negotiation summary."

The request must become an F25 task.

Do not allow analytics to directly modify ERP records.

---

## 21. F29 COPILOT INTEGRATION

Natural-language analytics must be available through the Universal Copilot.

Examples:

> "Analyze procurement this quarter."

> "Why are expenses higher?"

> "Forecast stock for next month."

> "Show me the biggest risks."

The Copilot should route analytical requests to F30.

---

## 22. F27 DOCUMENT INTEGRATION

Where authorized, analytical results may incorporate verified document intelligence.

Example:

> "Compare quoted supplier prices in these documents with historical purchase prices."

Document evidence must remain traceable to F27.

---

## 23. SAVED ANALYSES

Allow users to save analytical questions/configurations.

Store:

- name
- metric
- dimensions
- filters
- comparison
- forecast/scenario settings
- owner
- visibility
- refresh schedule

Do not store executable SQL generated by the model.

---

## 24. SCHEDULED ANALYTICS

Allow approved users to schedule:

- daily KPI summaries
- weekly management reports
- monthly financial analysis
- inventory forecasts
- supplier performance reports

Use the existing scheduler.

Every execution must use the user's/company's authorized data policy.

---

## 25. ALERT THRESHOLDS

Allow analytics to feed F26 thresholds.

Example:

> Alert me if procurement spend exceeds the monthly budget by 10%.

The actual detection should be deterministic.

---

## 26. CURRENCY HANDLING

Where financial metrics exist:

- preserve source currency
- support configured ERP base currency
- clearly identify conversions
- use approved ERP exchange-rate sources
- record exchange-rate date/source

Never silently mix currencies.

---

## 27. DATA QUALITY

Detect and communicate:

- missing values
- incomplete periods
- duplicate records
- inconsistent currency
- insufficient history
- unusual gaps

If data quality prevents reliable analysis, tell the user rather than generating a confident answer.

---

## 28. F23 OBSERVABILITY

Record:

- analytical request
- metric
- dimensions
- filters
- query definition
- execution time
- data volume
- model/provider
- forecast method
- cost
- result
- user feedback

Never log unnecessary sensitive data.

---

## 29. F24 GOVERNANCE

Every analytics operation must enforce:

- authentication
- tenant isolation
- RBAC
- record permissions
- data classification
- provider restrictions
- masking
- budget limits
- export restrictions
- retention

AI must not use analytics as a way to circumvent record-level permissions.

---

## 30. EXPORT SECURITY

If the ERP already supports exports, analytics may provide approved exports subject to F24.

Do not allow:

- unrestricted database dumps
- unauthorized bulk exports
- hidden sensitive columns
- external transmission without authorization

---

## 31. DATABASE

Create only required tables, following existing ERP conventions.

Potential tables:

- ai_metric_definitions
- ai_saved_analyses
- ai_forecasts
- ai_scenarios
- ai_analytics_runs

Reuse existing F23/F24/F29 infrastructure where appropriate.

All tables require tenant isolation and appropriate indexes.

---

## 32. HARD BOUNDARIES

The analytics system must NEVER:

- execute arbitrary SQL
- invent KPI values
- fabricate source data
- present forecasts as facts
- silently change production data
- treat correlation as proven causation
- bypass permissions
- expose unauthorized records
- mix currencies without disclosure
- execute scenario changes against production
- bypass F24
- bypass F23
- directly perform business actions instead of F25

---

# COMPLETION CRITERIA

F30 is complete when an authorized user can ask natural-language business questions, receive consistent KPI calculations, compare periods, drill into underlying data, identify trends, generate transparent forecasts, run safe what-if scenarios, receive evidence-based explanations, and turn recommendations into governed F25 tasks.

The architecture should be:

**ERP Data → Controlled Metrics → Analysis → Forecast/Scenario → Evidence → AI Explanation → F26/F25/F29**

with every operation governed by **F24** and observable through **F23**.