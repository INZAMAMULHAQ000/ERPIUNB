# AI F31 — Process Intelligence & Workflow Optimization

Implement the next major AI capability in the existing ERP:

## Core requirement

Build **AI Process Intelligence & Workflow Optimization** as a first-class ERP AI module.

**Technology constraints are mandatory:**
- Core PHP
- MySQL
- No frameworks
- Follow the ERP's existing architecture, authentication, RBAC, tenant/company isolation, UI conventions, database conventions, scheduler/cron, services, repositories, and APIs.
- Do not introduce a new framework or replace existing architecture.
- Reuse existing workflow, audit, activity, transaction, approval, and event infrastructure wherever available.

The purpose is to let users ask the ERP:

> “Where are our processes getting stuck?”

> “Why are purchase approvals taking so long?”

> “Show me the biggest bottlenecks this month.”

> “Compare the purchasing process between departments.”

> “How can we reduce invoice-processing time?”

> “What workflow changes would improve this?”

The AI should **analyze real ERP process data, explain bottlenecks, recommend improvements, simulate proposed changes, and prepare workflow changes for approval**, without silently changing production workflows.

---

## 1. Add AI Process Intelligence Center

Create:

**AI → Process Intelligence**

with:

- Overview
- Process Explorer
- Process Maps
- Bottlenecks
- Cycle Times
- SLA Breaches
- Rework
- Approval Delays
- Process Comparisons
- Recommendations
- Simulations
- Improvement Proposals
- Workflow Versions
- History

Use the existing ERP design system and navigation.

---

## 2. Process Discovery

Create a controlled process-discovery engine that derives processes from existing ERP events.

Possible event sources:

- workflow transitions
- approval events
- document status changes
- transaction creation/update/completion
- purchase/order lifecycle
- invoice lifecycle
- inventory movements
- support/service lifecycle
- employee processes
- integration events
- audit/activity events

Normalize events into a common structure:

```text
tenant/company
process_key
process_instance_id
entity_type
entity_id
event_name
event_type
actor
department
role
timestamp
status
source_module
metadata
```

Do not expose unrestricted database access to AI.

The process engine must use controlled repositories/services and allowlisted event sources.

---

## 3. Process Definitions

Create configurable process definitions.

Each definition should support:

- process name
- description
- source modules
- entity type
- start event
- completion event
- expected stages
- SLA targets
- optional business dimensions
- active/inactive
- visibility/permissions

Examples:

```text
Purchase Requisition → Purchase Order
Purchase Order → Goods Receipt
Invoice → Payment
Sales Quote → Sales Order
Sales Order → Delivery
Leave Request → Approval
Support Ticket → Resolution
```

Do not assume every ERP installation has all these processes. Discover and configure based on existing modules.

---

## 4. Process Mapping

Generate process maps from actual event history.

Display:

- stages
- transitions
- frequency
- average duration
- median duration
- slowest transitions
- rework loops
- abandoned instances
- exception paths
- SLA compliance

Example:

```text
Created
   ↓
Manager Approval
   ↓
Finance Approval
   ↓
Purchase Order
   ↓
Receipt
   ↓
Invoice
   ↓
Completed
```

Allow users to click a stage/transition and inspect supporting ERP records.

---

## 5. Bottleneck Detection

Implement deterministic bottleneck analysis first.

Detect:

- unusually long stage duration
- approval queues
- repeated reassignment
- excessive handoffs
- stalled records
- SLA breaches
- high-volume queues
- rework loops
- repeated rejection
- duplicate processing
- abandoned processes
- sudden process degradation
- department/branch bottlenecks

Use statistical thresholds where appropriate.

Do not allow the AI to invent bottlenecks.

Every detected issue must have evidence.

---

## 6. Cycle-Time Analysis

Calculate:

- total cycle time
- stage cycle time
- waiting time
- active processing time
- approval time
- queue time
- handoff time
- rework time

Support:

- average
- median
- P75
- P90
- P95
- minimum
- maximum

Where sample size is insufficient, explicitly state that the result is statistically weak or unavailable.

---

## 7. SLA Analysis

Support process-specific SLA definitions.

Detect:

- SLA met
- SLA at risk
- SLA breached

Provide:

- breach count
- breach percentage
- average breach duration
- worst stages
- responsible process stage/queue where supported by data
- trend over time

Do not assign blame to individuals without sufficient evidence.

Prefer neutral language such as:

> “Finance approval stage has the highest median waiting time.”

rather than unsupported conclusions about a person.

---

## 8. Rework Analysis

Detect process loops such as:

```text
Submitted
→ Rejected
→ Corrected
→ Resubmitted
```

Analyze:

- rejection frequency
- resubmission frequency
- average additional time
- common rejection reasons where structured data exists
- stages causing rework
- departments/process variants associated with rework

Provide evidence links.

---

## 9. Process Comparison

Allow comparisons by authorized dimensions:

- department
- branch
- company
- location
- supplier
- customer
- document type
- process version
- time period
- other approved ERP dimensions

Example:

> “Compare invoice processing between Abu Dhabi and Dubai.”

Return:

- volume
- median cycle time
- SLA compliance
- bottleneck stages
- rework rate
- exception rate

Respect F24 authorization and tenant isolation.

---

## 10. Natural-Language Process Questions

Integrate with **F29 AI ERP Copilot**.

Users should be able to ask:

> “Why are purchase orders slower this month?”

> “Which approval stage causes the most delay?”

> “Show me processes that breached SLA yesterday.”

> “Compare this quarter with last quarter.”

> “What is causing invoice-processing delays?”

> “Find the biggest workflow bottleneck.”

The Copilot must route process questions into this module instead of attempting unrestricted SQL or unsupported reasoning.

---

## 11. AI Explanation Layer

Use AI to explain deterministic process findings.

AI explanations must be grounded in:

- calculated metrics
- process events
- ERP records
- comparisons
- detected patterns
- configured business rules

Clearly distinguish:

**Verified data**

from

**AI interpretation**

from

**Recommendation**

Never present correlation as proven causation.

For example:

> “Finance approval has the highest median waiting time.”

is acceptable.

But:

> “Finance is causing the company's purchasing problems.”

is not acceptable unless supported by explicit evidence.

---

## 12. Improvement Recommendations

Create a recommendation engine that can suggest:

- workflow simplification
- approval consolidation
- routing changes
- SLA changes
- notification improvements
- queue balancing
- automation opportunities
- unnecessary handoff removal
- duplicate data-entry reduction
- validation earlier in the process
- exception handling improvements

Each recommendation must include:

- problem
- evidence
- proposed improvement
- expected benefit
- affected process
- affected stages
- risks
- assumptions
- confidence
- estimated impact where calculable

Recommendations are advisory only.

---

## 13. What-If Simulation

Implement process simulation without modifying production.

Users should be able to test hypothetical changes such as:

> “What if Finance approval was removed?”

> “What if orders under AED 5,000 required only manager approval?”

> “What if invoices were automatically routed to the correct department?”

The simulator should estimate:

- cycle-time impact
- queue impact
- approval workload
- SLA impact
- throughput
- potential risk

Clearly label results as:

**Simulation / Estimate — not actual ERP results.**

Never claim simulated results are guaranteed.

---

## 14. Improvement Proposal

Allow users to convert a recommendation into an **Improvement Proposal**.

Proposal should contain:

- current workflow
- identified problem
- evidence
- proposed change
- expected impact
- risk
- simulation results
- affected workflow
- affected stages
- proposed version
- creator
- status

Statuses:

```text
Draft
Under Review
Approved
Rejected
Scheduled
Applied
Rolled Back
```

---

## 15. Workflow Change Safety

Integrate with the existing workflow system and F24 governance.

AI must **never directly modify production workflows**.

Any workflow modification must:

1. generate a proposed configuration
2. validate it
3. show a diff
4. run available simulation/test validation
5. require appropriate approval
6. create a new workflow version
7. preserve the previous version
8. record a complete audit trail
9. support rollback

Never overwrite an existing workflow configuration invisibly.

---

## 16. Workflow Sandbox / Test Mode

Where technically possible, provide a sandbox/test execution mode.

A proposed workflow should be testable against:

- historical sample events
- representative records
- synthetic test cases

The test must not modify production records.

Display:

- expected transitions
- blocked transitions
- missing conditions
- permission failures
- validation failures
- potential loops
- affected approvals

---

## 17. Workflow Versioning

Add version support if the existing workflow engine does not already provide it.

Track:

- workflow
- version
- status
- configuration snapshot
- creator
- approval
- activation timestamp
- rollback information
- change summary

Never destroy historical versions.

---

## 18. Integration With Existing AI Modules

Integrate deeply with:

### F18 — Workflow Engine
Use the existing workflow definitions/execution infrastructure.

### F24 — Security, Governance & Guardrails
Every process query and workflow change must pass governance.

### F25 — Agent Runtime
Allow approved process-improvement tasks to be executed through the controlled agent runtime.

Example:

> “Prepare the proposed workflow change.”

F25 may prepare the proposal.

Production activation still requires required human approval.

### F26 — Proactive Intelligence
Create intelligence events for major process degradation.

Examples:

- “Invoice processing time increased 32%.”
- “Purchase approval SLA breaches increased significantly.”
- “A new bottleneck appeared in Finance approval.”

### F29 — ERP Copilot
Natural-language process questions should route here.

### F30 — Analytics
Reuse approved metrics and analytical calculations where appropriate.

### F23 — Observability
Track:

- process-analysis requests
- calculations
- AI explanations
- simulations
- recommendations
- proposal generation
- workflow-change attempts

---

## 19. Process Alerts

Allow monitored processes to generate alerts based on:

- cycle-time threshold
- SLA breach threshold
- throughput drop
- rejection increase
- rework increase
- queue growth
- unusual stage duration

Route alerts into F26.

Avoid duplicate alerts through deduplication/cooldowns.

---

## 20. Scheduled Process Analysis

Integrate with the existing scheduler/cron.

Support:

- daily process health analysis
- weekly bottleneck reports
- monthly process-performance reports
- SLA monitoring

Do not run unrestricted analysis across the entire database.

Use bounded/incremental processing.

---

## 21. Evidence and Drill-Down

Every important process finding must provide:

- source process
- date range
- metric calculation
- sample size
- relevant stages
- supporting records where authorized
- comparison baseline
- confidence/limitations

Users must be able to drill from:

```text
Insight
→ Process
→ Stage
→ Metric
→ Supporting ERP records
```

All record access must be permission-filtered.

---

## 22. Security Requirements

Reuse F24 centrally.

Mandatory protections:

- tenant isolation
- RBAC
- department restrictions
- sensitive-data masking
- row-level authorization
- approved metric definitions
- approved process sources
- no arbitrary SQL
- no arbitrary PHP
- no shell execution
- no arbitrary URLs
- no credential exposure
- no workflow permission bypass
- no self-approval
- no governance bypass

A user must never gain access to process records merely because the AI can calculate a metric involving them.

---

## 23. Prompt Injection Defense

Treat:

- ERP text
- comments
- descriptions
- documents
- imported data
- external integration data
- workflow metadata

as untrusted content.

Instructions contained inside business data must never override system/governance rules.

---

## 24. Database

Reuse existing tables where possible.

Only create new tables where necessary, such as:

```text
ai_process_definitions
ai_process_events
ai_process_runs
ai_process_metrics
ai_process_findings
ai_process_recommendations
ai_process_simulations
ai_process_proposals
ai_process_versions
ai_process_alerts
```

Use proper:

- primary keys
- foreign keys where compatible with the existing schema
- tenant/company keys
- indexes
- timestamps
- status fields
- audit metadata

Add indexes for process instance, process key, event timestamp, tenant/company, entity type/entity ID, and common analytical dimensions.

Do not duplicate existing workflow/event tables unnecessarily.

---

## 25. Performance

Process analysis must not repeatedly scan massive ERP tables during normal page requests.

Use:

- incremental processing
- indexed event queries
- aggregation
- cached metrics where appropriate
- scheduled background processing
- bounded date ranges
- pagination
- configurable analysis windows

Large process-analysis jobs should run asynchronously.

---

## 26. UI/UX

The interface should make process performance understandable to non-technical users.

Provide:

- KPI cards
- process maps
- bottleneck tables
- cycle-time charts
- SLA charts
- trend views
- comparison views
- evidence drawers
- recommendation cards
- simulation interface
- workflow diff viewer
- proposal approval interface

Use plain business language.

Avoid exposing internal AI reasoning or chain-of-thought.

Show concise explanations and evidence instead.

---

## 27. Audit

Record:

- who requested analysis
- process analyzed
- filters
- data scope
- calculations
- recommendation
- simulation
- proposal
- approvals
- workflow version changes
- rollback
- failures
- governance decisions

Audit records must be immutable or protected according to the ERP's existing audit architecture.

---

## 28. Emergency Controls

Respect F24 emergency controls.

If AI/process intelligence is disabled:

- no new AI process analysis
- no AI recommendations
- no simulations
- no AI workflow proposals

If workflow automation is disabled:

- process analysis may remain available if policy permits
- workflow changes must be blocked

If AI actions are disabled:

- analysis can remain read-only if policy permits
- proposal activation must be blocked

---

## 29. Hard Boundaries

The implementation must NEVER allow AI to:

- execute arbitrary SQL
- modify workflow tables directly
- execute PHP
- execute shell commands
- access unauthorized records
- bypass tenant isolation
- bypass RBAC
- approve its own workflow changes
- silently activate workflow changes
- delete workflow history
- fabricate process metrics
- fabricate evidence
- claim simulations are actual results
- expose secrets
- disable F24 governance
- override emergency controls
- modify its own permissions
- silently change production business logic

---

## 30. Testing Requirements

After implementation, verify all of the following.

### A. Process Discovery
1. Create/use a known ERP process with several lifecycle events.
2. Verify the process is discovered correctly.
3. Verify event ordering.
4. Verify process instances are grouped correctly.
5. Verify tenant isolation.

### B. Metrics
6. Verify total cycle time.
7. Verify stage duration.
8. Verify waiting time.
9. Verify median/P90/P95.
10. Compare calculations against manually verified ERP records.

### C. Bottlenecks
11. Create an intentionally slow workflow stage.
12. Verify it is detected.
13. Verify supporting evidence.
14. Verify no unsupported causal claim.

### D. SLA
15. Configure an SLA.
16. Create compliant and breached records.
17. Verify correct classification.
18. Verify breach duration and percentage.

### E. Rework
19. Create rejected/resubmitted workflow records.
20. Verify rework detection.
21. Verify additional cycle-time calculation.

### F. Comparison
22. Compare two departments/branches.
23. Verify only authorized data appears.
24. Verify comparison calculations.

### G. Copilot
25. Ask F29:
   - “Where are our biggest process bottlenecks?”
   - “Why are invoices slower this month?”
26. Verify correct routing to F31.
27. Verify evidence links.

### H. Recommendations
28. Generate a process recommendation.
29. Verify evidence, assumptions, risks, and confidence.
30. Verify recommendation does not automatically change production.

### I. Simulation
31. Create a hypothetical workflow change.
32. Run simulation.
33. Verify production data remains unchanged.
34. Verify result is clearly labeled as an estimate.
35. Test insufficient historical data.

### J. Workflow Proposal
36. Convert a recommendation into a proposal.
37. Verify workflow diff.
38. Verify validation.
39. Verify approval requirement.
40. Verify production workflow remains unchanged before approval.

### K. Workflow Versioning
41. Approve a valid change.
42. Verify a new workflow version is created.
43. Verify old version remains available.
44. Verify audit trail.
45. Test rollback.

### L. Governance
46. Test unauthorized process access.
47. Test restricted department access.
48. Test sensitive records.
49. Test unauthorized workflow modification.
50. Verify F24 denies every prohibited action.

### M. Prompt Injection
51. Put malicious instructions inside an ERP comment/document/process field.
52. Ask AI to analyze the process.
53. Verify the embedded instructions cannot alter AI behavior or governance.

### N. F25
54. Ask F29 to prepare a workflow improvement.
55. Verify F25 handles the task.
56. Verify approval remains required before production activation.

### O. F26
57. Create a measurable process degradation.
58. Verify F26 can receive the process intelligence alert.
59. Verify deduplication/cooldown.

### P. F23
60. Verify process analysis appears in AI observability.
61. Verify latency, provider/model, cost, failures, and tool usage where applicable.

### Q. Performance
62. Test with a large event history.
63. Verify page requests remain responsive.
64. Verify heavy analysis moves to background processing.
65. Verify indexes are used appropriately.

### R. Kill Switch
66. Disable AI/process intelligence through F24.
67. Verify analysis/recommendations stop.
68. Verify workflow activation cannot bypass the emergency control.

### S. Regression
69. Run regression testing across:

**F9 → F11 → F13 → F15 → F16 → F17 → F18 → F19 → F20 → F21 → F22 → F23 → F24 → F25 → F26 → F27 → F28 → F29 → F30 → F31**

Fix any regression before considering F31 complete.

## Completion criteria

F31 is complete only when:

- process data is grounded in actual ERP events
- bottlenecks and metrics are reproducible
- AI explanations are evidence-based
- recommendations are advisory
- simulations cannot modify production
- workflow changes require governance and approval
- workflow versions and rollback work
- F24 cannot be bypassed
- F25/F26/F29/F30/F23 integrations work
- tenant/RBAC isolation is verified
- performance is acceptable on realistic ERP data
- all regression tests pass