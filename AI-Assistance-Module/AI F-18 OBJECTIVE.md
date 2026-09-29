Implement **AI F18 — AI Workflow Builder & Visual Automation Studio** in the existing **Core PHP + MySQL ERP**.

### Objective

Build a user-friendly visual workflow system that allows authorized non-technical users to create ERP automations and AI-assisted workflows without writing code.

This extends F13 Automations and F17 Skills.

The user should be able to say:

> “When a purchase requisition is pending for more than 7 days, notify the procurement manager.”

The AI can convert the request into a **workflow draft**, which the user can visually review, configure, test, and enable.

The system must remain controlled:

- No arbitrary code execution
- No arbitrary SQL
- No unrestricted APIs
- No unsafe autonomous writes
- All workflow nodes must come from registered capabilities

---

# 1. Workflow Studio

Create:

```text
AI → Workflow Studio
```

Main capabilities:

- Create workflow
- AI-assisted workflow generation
- Visual workflow canvas
- Trigger configuration
- Condition builder
- Action builder
- AI analysis nodes
- Notification nodes
- Approval/confirmation nodes
- Delay/schedule nodes
- Testing
- Versioning
- Publish/enable/disable
- Execution history

The interface should be understandable to non-technical ERP users.

---

# 2. Workflow Architecture

Conceptual structure:

```text
Trigger
   ↓
Condition
   ↓
Action
   ↓
Optional AI Analysis
   ↓
Decision
   ↓
Next Action
```

Example:

```text
Purchase Requisition Updated
        ↓
Status = Pending
        ↓
Pending > 7 Days
        ↓
Notify Procurement Manager
```

A workflow is a structured graph of registered nodes.

Do not allow arbitrary executable logic.

---

# 3. Workflow Registry

Create centralized workflow definitions.

Each workflow should contain:

- ID
- Name
- Description
- Company scope
- Module
- Version
- Status
- Trigger configuration
- Graph definition
- Safety classification
- Created by
- Approved by
- Created at
- Updated at

Suggested statuses:

```text
draft
testing
approved
published
enabled
disabled
archived
```

Published versions must not be destructively overwritten.

---

# 4. Workflow Node Registry

Create a registry of allowed node types.

Initial categories:

```text
Triggers
Conditions
Actions
AI
Control Flow
Human Interaction
```

Example node types:

```text
event_trigger
schedule_trigger
manual_trigger

field_condition
threshold_condition
permission_condition

read_tool
registered_action
notification
create_task

ai_analysis
knowledge_search

if_else
switch
delay
wait_until

approval_request
confirmation_required
```

Every node must have:

- Unique type
- Input schema
- Output schema
- Validation rules
- Permission requirements
- Safety classification

---

# 5. Trigger Nodes

Support controlled triggers.

### Event Trigger

Example:

```text
purchase_requisition.updated
```

Must reuse the registered event architecture from F13.

---

### Schedule Trigger

Examples:

```text
Every Monday at 09:00
Daily at 18:00
Every hour
```

Reuse scheduler constraints.

---

### Manual Trigger

Allow an authorized user to start a workflow manually.

Example:

```text
Run Monthly Procurement Review
```

---

Do not allow arbitrary system events.

---

# 6. Condition Builder

Create non-technical condition configuration.

Examples:

```text
Status equals Pending
Amount greater than 10,000
Created date older than 7 days
```

Conditions must map to:

- Registered fields
- Registered operators
- Valid data types

Supported operators should include:

```text
equals
not_equals
greater_than
less_than
greater_than_or_equal
less_than_or_equal
contains
in
not_in
is_empty
is_not_empty
older_than
newer_than
```

Do not allow raw SQL conditions.

---

# 7. Control Flow

Support controlled graph execution.

Initial nodes:

```text
if_else
switch
delay
wait_until
end
```

Example:

```text
Condition
   ↓
IF
 ├── Yes → Notify Manager
 └── No  → End
```

Implement:

- Maximum graph depth
- Maximum nodes
- Loop prevention
- Execution timeout

Do not allow uncontrolled recursive loops.

---

# 8. Registered Action Nodes

Action nodes must reuse AI F9.

Examples may include:

- Create Task
- Request ERP Action
- Send internal notification
- Update workflow state

For ERP writes:

```text
Workflow
    ↓
Action Proposal
    ↓
Required Approval / Confirmation
    ↓
Execution
```

A workflow must never bypass action confirmation policies.

---

# 9. AI Analysis Nodes

Allow controlled AI analysis.

Example:

```text
Retrieve Pending Requisitions
        ↓
AI Analysis
        ↓
Classify Urgency
```

AI nodes must:

- Receive limited structured data
- Use configured provider/model
- Have output schema
- Validate output
- Respect AI budgets/limits

Do not allow unrestricted prompts with arbitrary ERP data.

---

# 10. AI Decision Nodes

Support AI-assisted decisions only where explicitly configured.

Example:

```text
Analyze request description
↓
Classify:
urgent
normal
needs_review
```

AI output must be schema-constrained.

For high-risk decisions:

- AI may recommend
- Human or deterministic rules must decide

Do not allow AI to autonomously:

- Approve financial transactions
- Reject employees
- Make legal decisions
- Execute unrestricted ERP changes

---

# 11. Knowledge Nodes

Allow workflows to query approved Knowledge collections.

Example:

```text
New Procurement Request
        ↓
Check Procurement Policy
        ↓
Return Relevant Approval Rule
```

Must reuse F11 access controls.

---

# 12. Notification Nodes

Support internal notifications.

Inputs:

- Recipient resolver
- Title template
- Summary template
- Severity
- Navigation target

Recipient selection must use registered resolvers.

Do not allow arbitrary external recipients.

---

# 13. Task Nodes

Reuse F10 Task Engine.

Example:

```text
Delayed Requisition
        ↓
Create Review Task
        ↓
Assign Procurement Manager
```

Task creation must respect permissions.

---

# 14. Human Approval Nodes

Support explicit human approval steps.

Example:

```text
Workflow
↓
Prepare Action
↓
Request Manager Approval
↓
Approved?
 ├── Yes → Continue
 └── No → End
```

Store approval state.

Do not treat AI approval as human approval.

---

# 15. Delay and Wait Nodes

Support controlled asynchronous workflow states.

Examples:

```text
Wait 24 hours
Wait until Monday
Wait until approval received
```

Workflow must persist state.

Do not keep PHP processes running while waiting.

Use scheduler-based resumption.

---

# 16. Workflow Execution Engine

Create reusable execution engine.

Conceptual lifecycle:

```text
Triggered
↓
Validate Workflow
↓
Create Run
↓
Execute Node
↓
Persist State
↓
Next Node
↓
Wait / Resume
↓
Complete
```

Statuses:

```text
queued
running
waiting
waiting_approval
completed
failed
cancelled
expired
```

Every node execution should be recorded.

---

# 17. Workflow Run State

Store:

- Workflow ID
- Workflow version
- Company
- Trigger context reference
- Current node
- Execution state
- Started at
- Completed at
- Error summary

Do not store unnecessary full ERP datasets repeatedly.

Use references where possible.

---

# 18. Workflow Node Execution History

Store:

- Node ID
- Node type
- Started
- Completed
- Status
- Duration
- Safe output summary
- Error category

This allows debugging without storing secrets or full prompts.

---

# 19. AI-Assisted Workflow Generation

Allow user request:

> “When a purchase requisition is overdue, notify the procurement manager and create a review task.”

Flow:

```text
Natural Language
↓
AI Workflow Draft
↓
Schema Validation
↓
Registered Node Validation
↓
Permission Check
↓
Visual Preview
↓
User Edit
↓
Test
↓
Approval
↓
Enable
```

AI-generated workflows must always begin as:

```text
draft
```

Never auto-enable them.

---

# 20. Visual Workflow Canvas

Build a visual workflow editor using existing frontend technology.

Since this ERP uses:

```text
Core PHP
MySQL
No Framework
```

Do not introduce a heavy frontend framework.

Use lightweight vanilla JavaScript and existing UI assets.

Features:

- Drag node
- Connect nodes
- Edit node
- Delete node
- Validation indicators
- Zoom if practical
- Workflow minimap optional

If a full drag-and-drop graph is too complex for the current frontend architecture, implement a structured visual flow editor first.

Do not compromise backend safety for UI convenience.

---

# 21. Workflow Validation

Before testing or publishing validate:

- Valid graph
- Valid trigger
- No disconnected required nodes
- Valid schemas
- Registered nodes only
- Permission requirements
- Action safety
- Loop prevention
- Node limits
- Dependency availability

Display validation errors clearly.

---

# 22. Workflow Testing

Create test mode.

Administrator/user can provide test input.

Example:

```text
Test Event:

Purchase Requisition
Status: Pending
Age: 10 Days
```

Expected:

```text
Trigger matched ✓
Condition matched ✓
Notification would send ✓
Task would create ✓
```

Test mode must:

- Not create production ERP records
- Not send real notifications unless explicitly safe/test-only
- Not call destructive actions
- Use dry-run mode

---

# 23. Workflow Versioning

Implement immutable versions.

Example:

```text
Overdue Requisition Alert

v1 → Enabled
v2 → Draft
v2 → Tested
v2 → Approved
v2 → Enabled
```

Existing workflow runs remain associated with their original version.

Do not mutate active execution graphs.

---

# 24. Publishing and Approval

Workflow lifecycle:

```text
Draft
↓
Testing
↓
Approved
↓
Published
↓
Enabled
```

For high-risk workflows require administrator approval.

Safety classification:

```text
low
medium
high
restricted
```

Policy determines approval requirements.

---

# 25. Workflow Permissions

Add only if missing:

```text
ai.workflows.view
ai.workflows.create
ai.workflows.edit
ai.workflows.test
ai.workflows.publish
ai.workflows.enable
ai.workflows.approve
ai.workflows.run
```

A workflow cannot elevate permissions.

At runtime:

- Trigger context is validated
- Workflow permissions checked
- Underlying action/tool permissions checked

---

# 26. Workflow Templates

Create reusable templates.

Initial templates:

```text
Event → Condition → Notification

Schedule → Read Tool → AI Summary → Notification

Event → Condition → Create Task

Manual → Read Tool → Analysis → Report

Event → Human Approval → Action
```

Templates are starting points only.

---

# 27. Workflow Marketplace Architecture

Do NOT build a public marketplace yet.

But architect workflow templates so future support is possible for:

- System templates
- Company templates
- Module templates

Do not add external downloading/importing in this phase.

---

# 28. Execution Limits

Integrate with F16.

Implement:

- Maximum nodes per workflow
- Maximum runtime
- Maximum waiting duration
- Maximum tool calls
- Maximum AI calls
- Maximum concurrent runs
- Maximum retries

When exceeded:

- Stop safely
- Mark failure
- Log reason

---

# 29. Retry Policy

Support controlled retry for safe nodes.

Examples potentially retryable:

- Provider request timeout
- Temporary notification failure
- Read-only tool timeout

Never automatically retry:

- Non-idempotent writes
- Human approvals
- Unknown partial ERP modifications

---

# 30. Error Handling

Workflow errors should provide:

```text
Workflow failed at:
AI Analysis Node

Reason:
Provider timeout

Suggested action:
Retry safely
```

Do not expose:

- Stack traces
- API keys
- Raw internal exceptions

---

# 31. Workflow Monitoring

Create:

```text
AI → Workflow Studio → Runs
```

Features:

- Active runs
- Waiting runs
- Failed runs
- Completed runs
- Execution timeline
- Node history
- Safe retry
- Cancel run

---

# 32. Workflow Analytics

Track:

- Runs
- Completion rate
- Failure rate
- Average duration
- Most used workflows
- Trigger frequency
- AI usage
- Estimated cost

Use aggregated analytics.

---

# 33. Workflow Audit Logging

Audit:

- Created
- Edited
- Tested
- Approved
- Published
- Enabled
- Disabled
- Triggered
- Run started
- Approval requested
- Action requested
- Run completed
- Run failed
- Run cancelled

Do not duplicate sensitive payloads unnecessarily.

---

# 34. Management Integration

Integrate with:

```text
F13 Scheduler
F15 Command Center
F16 Management Center
F17 Skills
```

Management Center should show:

- Workflow health
- Active workflows
- Failed workflows
- Usage
- Dependencies

---

# 35. Command Center Integration

Allow users to say:

> “Create an automation for overdue requisitions.”

Command Center should:

```text
Detect automation/workflow intent
↓
Generate Workflow Draft
↓
Open Workflow Studio
```

Never enable workflow automatically.

---

# 36. Skills Integration

Skills may launch approved workflows.

Workflows may invoke Skills only if:

- Explicitly registered
- Recursion protected
- Permission checked
- Execution limits enforced

---

# 37. Kill Switch Integration

F16 Management Center must support:

```text
Disable Workflow Studio
Disable Workflow Execution
Disable AI Workflow Generation
Disable Scheduled Workflows
Disable Event Workflows
```

Server-side enforcement required.

---

# 38. Security Requirements

Mandatory:

- Registered node types only.
- No arbitrary SQL.
- No arbitrary PHP.
- No shell commands.
- No unrestricted external APIs.
- Permission checks.
- Tenant isolation.
- Workflow/action approval enforcement.
- Immutable versions.
- AI-generated drafts require review.
- Execution limits.
- Loop prevention.
- Safe retry.
- Full audit logging.
- Kill switch support.
- No client-side-only validation.

---

# Important Constraints

Do NOT implement:

- Zapier-style unrestricted integrations yet
- Arbitrary JavaScript execution
- Arbitrary PHP execution
- Direct database queries
- Automatic publishing of AI-generated workflows
- Automatic bypass of approvals
- Infinite loops
- Long-running PHP processes
- Hidden background execution

This step is:

**A Safe Visual Workflow Builder that allows non-technical ERP users to automate approved ERP processes.**

---

# Completion Criteria

AI F18 is complete when:

- Workflow Studio exists.
- Workflow registry exists.
- Node registry exists.
- Event/schedule/manual triggers work.
- Conditions work.
- Notifications work.
- Tasks work.
- AI analysis nodes work.
- Human approvals work.
- Delays/waits persist safely.
- Workflow execution engine works.
- Visual editor works.
- AI can generate workflow drafts.
- Validation/testing works.
- Versioning works.
- Monitoring works.
- Permissions enforced.
- Limits enforced.
- Kill switches integrated.

After implementation, provide:

- Files created
- Files modified
- Database changes
- Workflow architecture
- Node types
- Execution lifecycle
- Versioning strategy
- Testing approach
- AI draft generation flow
- Approval model
- Retry strategy
- Security controls
- Known limitations