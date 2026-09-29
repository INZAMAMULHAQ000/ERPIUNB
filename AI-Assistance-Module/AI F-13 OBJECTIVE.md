Implement **AI F13 — AI Automation Scheduler & Event Triggers** in the existing **Core PHP + MySQL ERP**.

### Objective

Extend AI F10 Automation Foundation so users can run approved automations through controlled:

- Manual triggers
- Scheduled triggers
- ERP event triggers

This phase introduces unattended execution for **safe read-only automations only**.

State-changing operations must still follow the AI F9 mandatory confirmation system unless a future explicitly approved automation policy is implemented.

Architecture:

```text id="f13flow"
Manual Trigger ───────┐
Scheduled Trigger ────┼──→ Automation Engine
ERP Event Trigger ────┘
                              ↓
                     Workflow Validation
                              ↓
                      Permission / Scope
                              ↓
                       Read-only Tools
                              ↓
                         Analysis
                              ↓
                     Result / Notification
```

---

# 1. Automation Trigger Architecture

Extend the existing Automation system with a centralized Trigger Manager.

Create a reusable trigger abstraction.

Supported trigger types:

```text id="trigtypes"
manual
scheduled
event
```

Each trigger must:

- Have a unique ID
- Belong to one automation
- Have configuration
- Be enabled/disabled
- Validate configuration
- Respect company scope
- Produce an auditable execution event

Do not let trigger definitions directly execute PHP code.

---

# 2. Scheduled Automation Infrastructure

Implement a scheduler compatible with Core PHP + MySQL.

Do not introduce a framework.

Recommended architecture:

```text id="schedflow"
Server Cron
    ↓
PHP Scheduler Command
    ↓
Find Due Automations
    ↓
Acquire Execution Lock
    ↓
Create Automation Run
    ↓
Execute Controlled Workflow
    ↓
Store Result
    ↓
Calculate Next Run
```

Create a CLI-safe scheduler entry point.

Example conceptual command:

```text id="croncmd"
php path/to/ai_automation_scheduler.php
```

The exact file path must follow existing ERP structure.

Do not expose scheduler execution through a public unauthenticated URL.

---

# 3. Cron Configuration Documentation

Provide administrator instructions for configuring the server cron.

Example concept:

```text id="cronexample"
* * * * * php /path/to/project/ai_automation_scheduler.php
```

Do not hardcode server paths in application source code.

Document:

- PHP CLI path
- Application path
- Logging
- Recommended frequency
- Locking behavior

The scheduler must still work safely if cron is delayed or runs twice.

---

# 4. Schedule Types

Implement structured schedule support.

At minimum:

### Interval

Examples:

```text id="intervalex"
Every 15 minutes
Every hour
Every day
```

### Daily

Examples:

```text id="dailyex"
Every day at 09:00
Every day at 18:30
```

### Weekly

Examples:

```text id="weeklyex"
Every Monday at 08:00
Every Friday at 17:00
```

Store timezone explicitly.

Use a safe timezone strategy consistent with ERP company/user settings.

Do not rely blindly on server timezone.

Do NOT initially support arbitrary cron expressions unless a robust parser already exists in the project.

---

# 5. Next Run Calculation

Create a reusable Schedule Calculator.

Responsibilities:

- Validate schedule configuration
- Calculate next run
- Handle timezone
- Handle missed runs
- Prevent rapid accidental loops

Store:

```text id="schedulefields"
last_run_at
next_run_at
```

Missed-run policy should be configurable conceptually.

For AI F13 use safe default:

```text id="missedpolicy"
skip_missed_runs
```

Do not run hundreds of historical missed executions after downtime.

---

# 6. Scheduler Locking

Prevent duplicate scheduled execution.

Handle:

- Cron overlap
- Multiple servers
- Slow automation
- Manual scheduler restart

Use database-backed locking or existing project locking infrastructure.

Requirements:

- Only one scheduler instance processes the same due automation.
- Locks expire safely.
- Stale locks can recover.
- A crashed process must not permanently block scheduling.

Do not rely only on PHP memory locks.

---

# 7. Scheduled Run Ownership

Scheduled automations execute under a defined automation owner.

Store:

- created_by
- execution_owner_id

At execution time:

- Verify owner still exists/active.
- Verify automation remains active.
- Revalidate permissions for every tool.

Critical rule:

A scheduled automation must never retain permissions after the owner loses them.

If required permission is revoked:

- Do not execute.
- Mark run safely.
- Log reason.

---

# 8. Event Trigger Architecture

Create an internal ERP Event Bus abstraction.

Do not implement a heavy external event system.

Events should be emitted from approved ERP lifecycle points.

Conceptual interface:

```text id="eventinterface"
emit(eventName, payload, context)
subscribe(eventName, handler)
```

The event system must not execute arbitrary subscriber code from database configuration.

Automation subscriptions should route through the validated Trigger Manager.

---

# 9. Initial ERP Events

Implement event architecture with a small set of safe Procurement events only where corresponding lifecycle events genuinely exist.

Examples only if valid:

```text id="procEvents"
procurement.requisition.created
procurement.requisition.updated
procurement.purchase_order.created
procurement.purchase_order.updated
```

Do not invent events disconnected from actual business logic.

Event emission must occur only after successful business transactions.

Avoid triggering automations before a database transaction is committed.

---

# 10. Event Trigger Configuration

Allow automation users to configure:

- Event name
- Optional filters
- Enabled status

Example:

```text id="eventconfig"
Trigger:
procurement.requisition.created

Condition:
department = Procurement
```

Filters must use structured validated fields.

Do NOT allow:

- SQL WHERE clauses
- PHP expressions
- Arbitrary code
- Eval
- User-supplied executable expressions

Create a safe filter schema.

---

# 11. Event Payload Safety

Event payloads should contain only required information.

Prefer:

```text id="payloadsafe"
event_name
record_type
record_id
company_id
timestamp
changed_fields
```

Avoid passing entire unrestricted database records.

The automation should retrieve additional data through authorized read-only tools.

Do not place secrets or credentials into event payloads.

---

# 12. Event Deduplication

Prevent duplicate event-triggered runs.

Create event identity/idempotency handling.

Example logical key:

```text id="eventkey"
event_name + record_id + event_timestamp/version
```

Requirements:

- Same event must not repeatedly launch identical automation runs.
- Retries must be safe.
- Duplicate event delivery should not duplicate automation execution.

---

# 13. Automation Execution Policy

For AI F13 unattended scheduled/event execution:

Allowed:

- Read-only ERP tools
- Knowledge retrieval
- Controlled reasoning
- Summary generation
- Report generation
- Notification preparation

Not allowed automatically:

- Create records
- Update records
- Delete records
- Approve workflows
- Reject workflows
- Financial posting
- Payments
- External side effects

If a workflow reaches an action step:

```text id="actionpause"
PAUSE
↓
Create Pending Action
↓
Require Authorized Human Confirmation
```

Do not execute write actions unattended.

---

# 14. Automation Safety Classification

Add workflow safety classification.

Example:

```text id="safetyclass"
read_only
confirmation_required
blocked
```

Before enabling scheduled/event triggers:

Validate entire workflow.

Rules:

### read_only

May run unattended.

### confirmation_required

May run, but must pause before state-changing step.

### blocked

Cannot be scheduled/event triggered.

The server determines classification.

Users cannot manually override it.

---

# 15. Automation Notifications

Create a notification abstraction for automation results.

Initially support:

```text id="notifytypes"
in_app
```

Architecture should allow future:

- Email
- Webhooks
- Slack
- Microsoft Teams

Do not implement arbitrary outbound webhooks in AI F13.

Notification examples:

```text id="notifyex"
Daily Procurement Summary Completed

3 requisitions require attention.
2 purchase orders are overdue.
```

Notification content must be generated from actual execution results.

---

# 16. Automation Run Result UI

Extend AI → Automation.

Show:

- Trigger type
- Last run
- Next run
- Run status
- Duration
- Result summary
- Failure summary

For event-triggered runs show:

- Event name
- Record reference where authorized

Do not expose sensitive event payloads unnecessarily.

---

# 17. Scheduled Automation UI

Allow users to configure:

```text id="scheduleui"
Trigger: Scheduled

Schedule:
○ Every 15 minutes
○ Hourly
○ Daily
○ Weekly

Time:
09:00

Timezone:
Asia/Dubai
```

Use timezone identifiers supported by PHP DateTime.

Validate server-side.

---

# 18. Event Automation UI

Allow users to configure:

```text id="eventui"
Trigger: ERP Event

Event:
Purchase Requisition Created

Optional Conditions:
Department = Procurement
```

Only show events:

- Registered
- Enabled
- Compatible with user's module permissions

Do not expose arbitrary internal events.

---

# 19. Trigger Enablement Validation

Before enabling a scheduled/event automation:

Validate:

- Automation active
- Workflow valid
- Workflow safety classification
- Trigger configuration valid
- Owner active
- Required permissions available
- Compatible model/provider available
- Execution limits configured

If validation fails:

Do not enable trigger silently.

Show clear administrator/user feedback.

---

# 20. Automation Run Concurrency

Add concurrency controls.

Configure:

- Maximum concurrent automation runs globally
- Maximum per company
- Maximum per automation

Prevent one failing automation from exhausting all resources.

If concurrency limit reached:

Use safe behavior:

```text id="concurrency"
skip_or_delay
```

Do not create unlimited queued runs.

---

# 21. Execution Timeout

Each unattended automation must have:

- Maximum runtime
- Maximum steps
- Maximum tool calls

Reuse AI F10/F12 execution limit principles.

When exceeded:

- Stop execution.
- Mark run failed/limit reached.
- Log safe reason.
- Notify owner where appropriate.

---

# 22. Error Handling and Retry

Create controlled retry policy.

Only retry transient read-only failures.

Examples:

- Temporary provider timeout
- Temporary database connection issue
- Temporary embedding provider issue

Do not automatically retry:

- Write actions
- Confirmation-required actions
- Unknown partial execution states

Configure:

- Maximum retries
- Backoff delay

Track retry attempts.

---

# 23. Automation Cancellation & Disable

Allow authorized users to:

- Disable automation
- Disable trigger
- Cancel eligible running automation

When disabled:

- No future runs start.
- Existing completed runs remain historical.

Define safe behavior for currently running automation:

- Finish current read-only step or stop safely at next checkpoint.

Do not kill processes unsafely.

---

# 24. Audit Logging

Log:

### Trigger Events

- Trigger created
- Trigger updated
- Trigger enabled
- Trigger disabled

### Scheduled Execution

- Scheduler detected due automation
- Lock acquired
- Run started
- Run completed
- Run failed
- Run skipped
- Retry attempted

### Event Execution

- Event received
- Event matched automation
- Duplicate event ignored
- Automation run created

Do not log:

- API keys
- Secrets
- Full sensitive payloads
- Hidden model reasoning

---

# 25. Permissions

Integrate with existing AI Automation permissions.

Add only if missing:

```text id="triggerperm"
ai.automation.schedule
ai.automation.event_trigger
ai.automation.enable
ai.automation.disable
```

All server-side.

A user must not configure an event/schedule that can access data they do not normally have permission to access.

---

# 26. Administrator Safety Settings

Add AI Automation Safety Settings.

Suggested settings:

```text id="autosettings"
Enable Scheduled Automations
Enable Event Automations
Maximum Runs Per Hour
Maximum Concurrent Runs
Default Maximum Runtime
Default Maximum Steps
Default Tool Call Limit
Default Retry Count
Default Retry Delay
Maximum Trigger Frequency
Allow Confirmation-Pausing Automations
```

Hard limits must remain server-controlled.

---

# 27. Database Changes

Extend existing automation schema rather than duplicating concepts where practical.

Add required fields/tables for:

- Trigger configuration
- Schedule state
- Event subscriptions
- Scheduler locks
- Event idempotency
- Retry tracking
- Run trigger metadata
- Notifications

Follow existing MySQL conventions.

Use indexes for:

- next_run_at
- enabled status
- automation_id
- event identity
- lock state

---

# 28. Security Requirements

Mandatory:

- Scheduler CLI only.
- No public unauthenticated scheduler endpoint.
- Database-backed execution locking.
- Owner permissions revalidated.
- Tenant scope enforced.
- Workflow revalidated before run.
- Event filters structured and validated.
- No arbitrary SQL.
- No eval.
- No arbitrary PHP.
- No arbitrary webhooks.
- Write actions never auto-execute.
- Duplicate event protection.
- Execution limits.
- Concurrency limits.
- Safe retry policy.
- Sensitive payload minimization.
- Full audit trail.

---

# Important Constraints

Do NOT implement:

- Autonomous write execution
- Payment automation
- Approval automation
- Financial posting automation
- Arbitrary cron expressions
- Arbitrary webhook calls
- Arbitrary external API calls
- Arbitrary code execution
- Unlimited event recursion
- High-frequency uncontrolled triggers

This step is:

**Controlled Scheduled + Event-Triggered Read-Only AI Automation.**

---

# Completion Criteria

AI F13 is complete when:

- Scheduled triggers work.
- Event triggers work for supported ERP events.
- Scheduler runs safely through CLI/cron.
- Duplicate execution is prevented.
- Owner permissions are revalidated.
- Workflows are safety-classified.
- Read-only workflows can execute unattended.
- Write actions pause for human confirmation.
- Notifications are created.
- Concurrency and timeout limits work.
- Event filters are safe.
- All trigger/run activity is audited.

After implementation, provide:

- Files created
- Files modified
- Database changes
- Scheduler entry point
- Cron setup instructions
- Trigger types implemented
- ERP events implemented
- Safety classification approach
- Locking/idempotency approach
- Retry policy
- Security controls
- Known limitations