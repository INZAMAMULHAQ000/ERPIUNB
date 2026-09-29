# AI F26 — Proactive Intelligence, Alerts & Recommendations

Build **AI F26 — Proactive Intelligence & Recommendations** for the existing ERP.

## NON-NEGOTIABLE TECH STACK

- **Core PHP + MySQL only**
- **No frameworks**
- Reuse the existing ERP architecture, authentication, RBAC, UI, scheduler, notifications, AI services, F23 observability, F24 governance, and F25 Agent Runtime.
- Do not introduce Laravel, Symfony, CodeIgniter, React, Vue, Node.js, Python services, or another application framework.
- Do not duplicate existing functionality.

## OBJECTIVE

Turn the AI from something users only ask questions to something that can **proactively notice important situations and recommend useful actions**.

Examples:

> "Three purchase orders are overdue and two suppliers have not responded."

> "Inventory for Product X is projected to fall below its reorder level within 8 days."

> "A customer has not paid an invoice that is significantly overdue."

> "Expenses in this department increased unusually compared with previous periods."

The AI should detect, explain, prioritize, and optionally create a controlled F25 task.

**Detection → Evidence → Explanation → Recommendation → Approval → Optional Action**

The AI must **never silently perform business actions merely because it detected an issue.**

---

## 1. AI → INTELLIGENCE CENTER

Add:

**AI → Intelligence**

Sections:

- Overview
- Alerts
- Recommendations
- Insights
- Rules
- Monitors
- Watchlists
- Trends
- Dismissed
- History

Dashboard should show:

- critical issues
- important alerts
- recommendations
- emerging trends
- monitored KPIs
- unresolved issues
- recent AI actions

---

## 2. INTELLIGENCE EVENTS

Create a standard intelligence-event structure.

Each event should contain:

- event ID
- tenant/company
- source module
- source record
- detection type
- severity
- confidence
- title
- explanation
- evidence
- detected time
- status
- recommendation
- suggested action
- expiration
- assigned user/team
- related F25 task if created

Statuses:

- new
- acknowledged
- snoozed
- action_pending
- actioned
- dismissed
- expired

---

## 3. DETECTION ENGINE

Implement server-side detection mechanisms.

Support:

### Rule-based detection

Examples:

- overdue invoices
- overdue purchase orders
- stock below threshold
- expiring contracts
- missing approvals
- failed workflows
- unusual transaction counts

### Threshold detection

Examples:

- amount > configured threshold
- quantity below threshold
- aging beyond configured days
- KPI outside configured range

### Trend detection

Compare:

- current period vs previous period
- current month vs historical average
- department vs company
- supplier/customer performance over time

### Anomaly detection

Use deterministic/statistical methods where possible.

Do not require an LLM to perform basic arithmetic or threshold detection.

---

## 4. AI EXPLANATION LAYER

Once a deterministic detector identifies an event, AI may explain it in human language.

Example:

**Alert: Supplier performance declining**

Explain:

- what changed
- relevant historical comparison
- affected records
- why it matters
- confidence
- recommended next step

The explanation must be grounded in retrieved ERP evidence.

Never allow the model to invent supporting numbers.

---

## 5. EVIDENCE PANEL

Every important insight should allow the user to inspect its evidence.

Show:

- source records
- dates
- amounts
- statuses
- comparisons
- calculation inputs
- detection rule
- confidence

Respect F24 record permissions.

If the user cannot access a record, it must not appear in the evidence.

---

## 6. RECOMMENDATION ENGINE

For each intelligence event, optionally generate:

- recommendation
- urgency
- expected benefit
- affected records
- proposed action
- estimated impact
- required approval

Examples:

> Review overdue purchase orders.

> Contact affected suppliers.

> Create a reorder request.

> Review unusual expenses.

> Escalate overdue customer accounts.

Recommendations are **not actions**.

---

## 7. ONE-CLICK CONTROLLED ACTIONS

Where appropriate, show:

- Review
- Create Task
- Ask AI
- Draft Action
- Execute
- Snooze
- Dismiss

If action is required, create an **F25 Agent Task** rather than allowing the intelligence engine to directly modify ERP data.

Every resulting action must pass F24 governance.

---

## 8. NATURAL-LANGUAGE FOLLOW-UP

From an insight, allow users to ask:

> "Why is this happening?"

> "Show me the affected suppliers."

> "What should I do?"

> "Prepare a follow-up."

> "Fix this."

The AI should preserve the insight context while respecting permissions and governance.

"Fix this" should create an F25 task subject to autonomy/confirmation rules.

---

## 9. WATCHLISTS

Allow users to monitor:

- customers
- suppliers
- products
- invoices
- purchase orders
- contracts
- projects
- departments
- KPIs

A watchlist can trigger intelligence when configured conditions change.

Examples:

> Notify me when Supplier ABC has another late delivery.

> Watch this customer's overdue balance.

> Tell me if Product X stock falls below 100.

Store watchlists with proper tenant/user permissions.

---

## 10. MONITORS

Administrators should be able to configure reusable monitors.

Each monitor should support:

- name
- module
- data source
- condition
- threshold
- frequency
- severity
- notification target
- recommendation
- enabled/disabled

Do not allow arbitrary SQL.

Use approved ERP repositories/services and controlled query definitions.

---

## 11. SCHEDULER INTEGRATION

Use the existing ERP scheduler/cron architecture.

Support:

- hourly
- daily
- weekly
- configurable intervals

The detection engine should process only the required data.

Avoid repeatedly scanning the entire database unnecessarily.

Use:

- incremental processing
- indexed queries
- last-run checkpoints
- relevant date ranges
- deduplication

---

## 12. DUPLICATE DETECTION

The same underlying issue should not generate hundreds of identical alerts.

Implement event fingerprints based on appropriate combinations of:

- tenant
- detector
- source record
- condition
- relevant period

Allow configured recurrence when the underlying situation meaningfully changes.

---

## 13. PRIORITY & SEVERITY

Support:

- Informational
- Low
- Medium
- High
- Critical

Priority should consider:

- business impact
- financial impact
- urgency
- affected record count
- confidence
- configured policy

Do not let an LLM arbitrarily override administrator-defined severity rules.

---

## 14. NOTIFICATIONS

Integrate with the ERP's existing notification system.

Support appropriate channels already available in the ERP.

Notifications should include:

- concise issue
- severity
- why it matters
- action link
- affected module/record
- timestamp

Do not send sensitive information through an external channel unless F24 explicitly permits it.

---

## 15. DAILY AI BRIEFING

Add an optional personalized:

**AI Daily Briefing**

Example:

### Today's priorities

- 5 overdue purchase orders
- 3 high-value overdue invoices
- 2 low-stock items
- 1 contract expiring soon

### Recommended actions

1. Review overdue supplier orders.
2. Follow up on high-value receivables.
3. Reorder Product X.

Users can open each item and investigate.

Generate the briefing from verified ERP data.

---

## 16. ROLE-BASED INTELLIGENCE

Different users should see relevant insights.

Examples:

Finance:

- overdue receivables
- unusual expenses
- payment risks

Procurement:

- supplier delays
- overdue purchase orders
- price changes

Inventory:

- low stock
- excess stock
- demand trends

Management:

- KPI changes
- major risks
- high-impact issues

Do not expose information merely because an insight is useful.

F24 permissions remain authoritative.

---

## 17. PERSONALIZATION

Allow users to configure:

- topics
- severity threshold
- notification frequency
- monitored entities
- briefing preference
- snooze settings

Store personalization per user/company.

---

## 18. ALERT FEEDBACK

Allow users to mark an insight:

- useful
- not useful
- incorrect
- duplicate
- already handled

Capture feedback for F23.

Do not automatically change detection logic from feedback.

Feedback may become an input to future evaluation/improvement.

---

## 19. EXPLANATION & TRUST

Every AI-generated insight must distinguish:

**Verified facts**

from

**AI interpretation**

from

**Recommendation**

Example:

**Verified:** 7 purchase orders are more than 14 days overdue.

**Interpretation:** Supplier delivery performance appears to be deteriorating.

**Recommendation:** Review the affected suppliers and consider escalation.

Never present speculation as fact.

---

## 20. F23 OBSERVABILITY

Integrate all intelligence activity with F23.

Record:

- detector
- query/data source
- detection result
- evidence
- model/provider if AI explanation was used
- latency
- cost
- generated recommendation
- user feedback
- resulting F25 task
- final action/outcome

Do not expose or log secrets.

---

## 21. F24 GOVERNANCE

Every intelligence operation must respect F24:

- tenant isolation
- RBAC
- data classification
- masking
- provider policies
- AI usage policies
- notification policies
- external communication restrictions
- action policies
- autonomy limits

Detection must not become a way to reveal unauthorized information.

If an insight contains restricted data, mask or suppress it according to F24.

---

## 22. F25 AGENT INTEGRATION

An insight may create an F25 task.

Example:

**Insight**

> 8 purchase orders are overdue.

**Suggested task**

> Review the 8 overdue POs, identify suppliers requiring follow-up, and prepare draft emails.

The F25 agent must still:

- create a plan
- pass governance
- respect limits
- request approval when required
- execute registered tools only
- verify results
- record everything

---

## 23. DATABASE

Create normalized tables following existing ERP conventions.

At minimum support:

- ai_intelligence_events
- ai_intelligence_evidence
- ai_intelligence_rules
- ai_intelligence_monitors
- ai_intelligence_watchlists
- ai_intelligence_feedback
- ai_intelligence_runs

Include:

- company/tenant ID
- user ownership where applicable
- timestamps
- status
- indexes
- audit fields

Use prepared statements everywhere.

---

## 24. PERFORMANCE

The intelligence engine must not degrade normal ERP performance.

Implement:

- indexed detection queries
- incremental processing
- batch processing
- execution limits
- scheduler locking
- duplicate-run prevention
- appropriate pagination
- configurable processing frequency

A failed detector must not stop other detectors from running.

---

## 25. SECURITY HARD BOUNDARIES

The intelligence system must NEVER:

- bypass permissions
- expose unauthorized records
- execute arbitrary SQL
- execute arbitrary PHP/shell commands
- call arbitrary URLs
- reveal credentials
- silently modify ERP records
- send external communications without required approval
- invent evidence
- claim an action occurred when it did not
- disable F24 governance
- bypass F23 audit/observability
- automatically change its own detection rules
- automatically promote speculative AI output to verified business facts

---

## 26. TEST DATA / DEMO DETECTORS

Create safe representative detectors for testing, such as:

1. Overdue invoice detector
2. Overdue purchase-order detector
3. Low-stock detector
4. Contract-expiry detector
5. Transaction-volume anomaly detector

Use existing ERP data models where possible.

Do not create duplicate business logic if equivalent ERP services already exist.

---

# COMPLETION CRITERIA

F26 is complete when the ERP can proactively detect meaningful business situations, explain them using verified evidence, prioritize them, recommend next steps, notify the appropriate user, and optionally hand the issue to F25 for controlled execution.

The system must remain **proactive but governed**:

**Detect → Explain → Recommend → Govern → Execute only when authorized → Verify → Learn through evaluation/feedback.**