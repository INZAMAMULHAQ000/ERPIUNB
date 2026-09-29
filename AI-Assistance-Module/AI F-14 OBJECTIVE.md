Implement **AI F14 — AI Notification Intelligence & Proactive Insights** in the existing **Core PHP + MySQL ERP**.

### Objective

Extend the AI module so the system can proactively identify important ERP situations and notify the right users with useful AI-generated insights.

This phase introduces:

- AI insight detection
- Insight rules
- Proactive notifications
- Priority classification
- User-specific relevance
- Insight summaries
- Navigation links
- Dismissal and feedback
- Insight history

The goal is to make the ERP feel more like a living AI assistant:

> “I noticed 6 purchase requisitions have been pending for more than 7 days. Two require urgent attention.”

However, the AI must remain grounded in actual ERP data.

No hallucinated alerts.

No autonomous state-changing actions.

---

# 1. Proactive Insight Architecture

Create a centralized AI Insight Engine.

Conceptual flow:

```text
ERP Data / Event
        ↓
Insight Trigger
        ↓
Insight Detector
        ↓
Read-only Tools / Queries
        ↓
Rule Evaluation
        ↓
AI Analysis (optional)
        ↓
Insight Validation
        ↓
Priority Classification
        ↓
Target User Resolution
        ↓
Notification
        ↓
User Interaction / Navigation
```

The Insight Engine must reuse existing systems where possible:

```text
AI F8  → Read-only tools
AI F10 → Tasks / execution concepts
AI F11 → Knowledge context
AI F12 → Agent capabilities where appropriate
AI F13 → Scheduler + event triggers
```

Do not duplicate ERP business logic unnecessarily.

---

# 2. Insight Types

Create architecture supporting:

```text
rule_based
ai_analyzed
hybrid
```

### Rule-Based

Example:

```text
If pending_purchase_requisitions > 10
→ Create insight
```

### AI-Analyzed

Example:

```text
Retrieve pending procurement data
→ AI identifies unusual patterns
→ Structured insight returned
```

### Hybrid

Example:

```text
Rule detects overdue requisitions
→ AI generates concise explanation
→ User receives insight
```

For initial implementation, prioritize:

```text
rule_based
hybrid
```

Do not allow unconstrained AI to scan the entire ERP database.

---

# 3. Insight Registry

Create a centralized Insight Registry.

Each insight definition should contain:

- Unique ID
- Name
- Description
- Module
- Category
- Insight type
- Enabled status
- Required permissions
- Data source/tool
- Evaluation frequency
- Threshold configuration
- Severity logic
- Target user resolver
- Navigation destination
- Deduplication strategy

Example:

```php
[
    'id' => 'procurement.pending_requisition_alert',

    'name' => 'Pending Purchase Requisitions',

    'module' => 'procurement',

    'type' => 'hybrid',

    'required_permissions' => [
        'actual.existing.procurement.permission'
    ],

    'evaluation' => [
        'tool' => 'procurement.search_purchase_requisitions'
    ],

    'navigation' => [
        'route' => 'actual_existing_route'
    ]
]
```

Use actual ERP permissions/routes.

Do not invent routes.

---

# 4. Initial Procurement Insights

Implement only insights based on genuinely available Procurement data.

Start with a small safe set.

Potential examples ONLY where data/status fields exist:

### A. Overdue Purchase Requisitions

Detect requisitions that have remained pending beyond a configurable period.

Example:

```text
6 Purchase Requisitions Need Attention

4 have been pending for more than 7 days.

[View Requisitions]
```

---

### B. High Pending Requisition Volume

Example:

```text
High Pending Procurement Activity

18 purchase requisitions are currently awaiting action.
```

Threshold configurable.

---

### C. Purchase Order Delays

Only if the Procurement module genuinely contains relevant dates/statuses.

Example:

```text
3 Purchase Orders May Be Delayed

Expected delivery dates have passed.
```

---

### D. Supplier Concentration Insight

Read-only analytical insight.

Example:

```text
Supplier Concentration Detected

62% of recent purchase orders involve one supplier.
```

Do not imply wrongdoing.

Present as an observation.

---

Do not implement financial-risk claims unless supported by real data and explicit business rules.

---

# 5. Insight Evaluation Engine

Create a reusable evaluator.

Logical lifecycle:

```text
Load Insight Definition
        ↓
Validate Enabled Status
        ↓
Validate Permissions
        ↓
Retrieve Authorized Data
        ↓
Evaluate Conditions
        ↓
Generate Insight
        ↓
Validate Output
        ↓
Check Duplicate
        ↓
Store Insight
        ↓
Notify Users
```

The evaluator must execute only registered insight definitions.

Do not allow arbitrary user-created SQL queries.

---

# 6. AI Insight Analysis

Where AI is used:

The AI receives:

- Limited structured data
- Relevant context
- Defined output schema

The AI should return structured output such as:

```json
{
    "title": "High number of pending requisitions",
    "summary": "18 requisitions remain pending, including 6 older than 7 days.",
    "severity": "warning",
    "confidence": "high",
    "key_findings": [
        "6 requisitions are older than 7 days"
    ]
}
```

Server-side validation is mandatory.

Do not display raw AI output directly.

Do not allow AI to invent:

- Record counts
- Dates
- Financial amounts
- Policy violations

All factual values must come from retrieved ERP data.

---

# 7. Grounding Requirements

Every insight must have structured evidence.

Store evidence references such as:

- Record IDs
- Aggregate counts
- Date ranges
- Tool execution reference
- Evaluation timestamp

Example:

```text
Evidence:

Pending requisitions: 18
Older than 7 days: 6
Evaluation time: 2026-08-31 09:00
```

The UI may display simplified evidence.

AI-generated narrative must be derived from actual evidence.

---

# 8. Insight Severity

Create standard severity levels:

```text
info
notice
warning
critical
```

Severity should primarily be determined by server-side rules.

AI may provide optional explanation but should not freely assign critical severity without configured criteria.

Example:

```text
0–5 overdue → notice
6–10 overdue → warning
>10 overdue → critical
```

Thresholds should be configurable.

---

# 9. Insight Database Structure

Create database structures consistent with existing conventions.

## AI Insight Records

Logical fields:

- id
- insight_definition_id
- company_id
- source_type
- title
- summary
- severity
- category
- evidence_json
- navigation_json
- status
- first_detected_at
- last_detected_at
- resolved_at
- created_at
- updated_at

Suggested statuses:

```text
active
seen
dismissed
resolved
expired
```

---

## AI Insight Recipients

Logical fields:

- id
- insight_id
- user_id
- status
- seen_at
- dismissed_at
- created_at
- updated_at

Recipient-specific state should not alter the underlying insight globally.

---

## AI Insight Evaluations

Logical fields:

- id
- insight_definition_id
- company_id
- status
- evidence_summary
- records_evaluated
- insight_count
- duration_ms
- error_message
- evaluated_at

Do not store unnecessary raw sensitive ERP data.

---

# 10. Deduplication

Prevent notification spam.

Each insight definition must define a deduplication strategy.

Examples:

```text
same_definition + same_company + same_record_set
```

or:

```text
same_definition + same_company + same_day
```

Requirements:

- Repeated scheduler runs should not create duplicate alerts.
- Existing active insight should be updated rather than duplicated where appropriate.
- Meaningful changes may update the existing insight.
- Users should not receive the same notification repeatedly without change.

Store a deterministic evidence/deduplication hash.

---

# 11. Insight Lifecycle

Implement lifecycle:

```text
Detected
   ↓
Active
   ↓
Seen
   ↓
Dismissed
```

Also support:

```text
Active
   ↓
Condition No Longer Exists
   ↓
Resolved
```

A dismissed insight may remain historically recorded.

If the underlying condition becomes significantly worse later, a new insight may be generated according to deduplication policy.

---

# 12. Automatic Resolution

Where possible, periodically re-evaluate active insights.

Example:

```text
8 overdue requisitions
↓
User processes all requisitions
↓
Next evaluation finds 0 overdue
↓
Insight automatically resolved
```

Do not falsely claim resolution without verifying actual ERP data.

---

# 13. Notification Integration

Integrate with the ERP's existing notification system.

If no reusable notification system exists, create a minimal internal abstraction.

Initially support:

```text
in_app
```

Notification should contain:

- Title
- Short summary
- Severity
- Module
- Timestamp
- Navigation action where authorized

Example:

```text
⚠ Procurement Attention Needed

6 purchase requisitions have been pending for more than 7 days.

[Review Now]
```

Do not send repeated notifications unnecessarily.

---

# 14. AI Insights Center

Create:

**AI → Insights**

Features:

- Active insights
- Severity filter
- Module filter
- Date filter
- Seen/unseen state
- Dismiss insight
- View evidence
- Navigate to related ERP records
- Insight history

Suggested sections:

```text
Critical
Warnings
Information
Resolved
```

Use existing ERP UI conventions.

---

# 15. Dashboard Integration

Add a lightweight AI Insights widget to the main ERP dashboard if the dashboard architecture supports widgets.

Example:

```text
AI Insights

⚠ 6 requisitions need attention
ℹ High supplier concentration detected
✓ 2 previous issues resolved
```

Do not overload the dashboard.

Limit visible items.

Respect user permissions.

---

# 16. Navigation Integration

Every insight should optionally provide an authorized navigation destination.

Example:

```text
[View Pending Requisitions]
```

Navigation must use AI F7 principles:

- Validated route
- Permission check
- Tenant scope
- No arbitrary URL generation

Do not expose links to records the recipient cannot access.

---

# 17. Target User Resolution

Determine who should receive an insight.

Create controlled recipient resolvers.

Examples:

```text
insight_creator
module_managers
authorized_role
record_owner
department_manager
```

Only implement resolvers supported by existing ERP user/role architecture.

Do not let AI arbitrarily choose users.

Recipient resolution must be server-side.

---

# 18. User Preferences

Create optional AI Insight preferences.

Allow users to control:

- Enable/disable informational insights
- Enable/disable warning notifications where policy allows
- Preferred modules
- Notification frequency

Critical system-level alerts should not necessarily be suppressible if the ERP policy requires them.

Preferences must never bypass access control.

---

# 19. Insight Feedback

Allow users to provide simple feedback:

```text
Helpful
Not Helpful
Dismiss
```

Optionally allow a short reason.

Store feedback for future analysis.

Do not automatically retrain models from feedback in this phase.

Do not use feedback to silently alter ERP business rules.

---

# 20. Scheduler Integration

Use AI F13 scheduler architecture.

Create registered scheduled insight evaluation jobs.

Example:

```text
Every hour
→ Evaluate Procurement Insights
```

Do not run expensive evaluations continuously.

Configure:

- Evaluation frequency
- Maximum records analyzed
- Maximum runtime
- Maximum concurrent evaluations

---

# 21. Event-Based Insight Evaluation

Where useful, integrate with AI F13 event triggers.

Example:

```text
procurement.requisition.updated
        ↓
Re-evaluate relevant insight
```

Do not evaluate every insight after every ERP event.

Create explicit mappings:

```text
ERP Event
    ↓
Relevant Insight Definitions
```

---

# 22. Insight Execution Limits

Implement:

- Maximum insight evaluations per scheduler cycle
- Maximum records analyzed
- Maximum AI calls
- Maximum runtime
- Maximum active insights per company
- Maximum notifications per user per period

When limits are reached:

- Stop safely
- Log event
- Avoid uncontrolled backlog

---

# 23. AI Provider Compatibility

AI-generated insight analysis requires a configured compatible provider/model.

Required capabilities where applicable:

```text
chat
structured_output
```

Rule-based insights must continue working even if AI provider is unavailable.

Hybrid insight behavior should degrade safely:

```text
Rule detects condition
↓
AI unavailable
↓
Generate deterministic rule-based summary
```

Do not lose important alerts solely because an AI provider fails.

---

# 24. Insight Audit Logging

Log:

- Insight definition evaluated
- Data retrieval completed
- Condition detected
- Insight created
- Insight updated
- Notification created
- User viewed insight
- User dismissed insight
- Insight resolved
- Evaluation failed

Do not log:

- API keys
- Hidden chain-of-thought
- Full unnecessary ERP datasets

---

# 25. Permissions

Integrate with existing AI permissions.

Add only if missing:

```text
ai.insights.view
ai.insights.manage
ai.insights.dismiss
ai.insights.configure
```

Server-side permission enforcement required.

Users must not see insights containing data from modules they cannot access.

---

# 26. Administrator Configuration

Create AI Insight settings.

Suggested settings:

```text
Enable AI Insights
Enable Dashboard Insights
Maximum Insights Per User
Maximum Notifications Per Hour
Default Evaluation Frequency
Maximum Evaluation Runtime
Maximum Records Per Evaluation
Enable AI Narrative Generation
Insight Retention Period
```

Hard safety limits remain server-side.

---

# 27. Security Requirements

Mandatory:

- Insights use authorized read-only data.
- No direct arbitrary SQL.
- No AI-generated database queries.
- Tenant/company isolation.
- Recipient permissions validated.
- Evidence grounded in actual data.
- AI output schema validated.
- Deduplication prevents notification spam.
- Navigation links validated.
- No arbitrary user targeting by AI.
- No hidden reasoning exposed.
- No autonomous state changes.
- Full audit trail.

---

# Important Constraints

Do NOT implement:

- Automatic corrective actions
- Automatic approvals
- Automatic record modification
- Autonomous financial decisions
- Unrestricted anomaly detection across all data
- AI-generated SQL
- Unbounded continuous monitoring
- Arbitrary external notifications/webhooks

This step is:

**Grounded Proactive AI Insights + Intelligent Notifications**

---

# Completion Criteria

AI F14 is complete when:

- Insight Registry exists.
- Procurement insights are implemented.
- Insights are based on actual ERP evidence.
- Severity classification works.
- Deduplication prevents spam.
- Insights appear in AI Insights Center.
- In-app notifications work.
- Navigation links work safely.
- Insights resolve when conditions disappear.
- Scheduler/event integration works.
- User feedback works.
- Permissions and tenant isolation are enforced.
- No autonomous write actions occur.

After implementation, provide:

- Files created
- Files modified
- Database changes
- Insight definitions implemented
- Evaluation strategy
- Deduplication strategy
- Notification integration
- Permission model
- Scheduler/event integration
- Security controls
- Known limitations