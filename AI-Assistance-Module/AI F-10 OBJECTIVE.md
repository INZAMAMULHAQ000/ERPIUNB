Implement **AI F10 — AI Task & Automation Foundation** in the existing Core PHP + MySQL ERP.

### Objective

Build the foundation for users to ask AI to perform multi-step tasks and create reusable automations.

This step introduces:

- AI task management
- Task planning
- Step-by-step execution plans
- Human approval checkpoints
- Task status tracking
- Automation definitions
- Trigger architecture
- Execution history

Important:

This step does NOT allow unrestricted autonomous execution.

Any state-changing step must continue using the AI F9 confirmation/action framework.

---

# 1. Create AI Task Architecture

Create a central AI Task system.

Conceptual flow:

```text id="8xdl6s"
User Request
      ↓
AI understands objective
      ↓
Task Planner
      ↓
Structured Task Plan
      ↓
User Review
      ↓
Task Execution
      ↓
Read Tools / Approved Actions
      ↓
Progress Tracking
      ↓
Final Result
```

A task is a structured multi-step objective.

Example:

> Find all pending purchase requisitions from this month and prepare a summary.

Possible plan:

```text id="zix1vy"
1. Search purchase requisitions
2. Filter pending records
3. Collect summary data
4. Generate user-facing summary
```

Read-only tasks may execute automatically after user starts them.

Write/action steps must pause for confirmation according to AI F9.

---

# 2. Database Structure

Create required MySQL tables following existing ERP conventions.

## AI Tasks

Logical fields:

- id
- user_id
- company_id / tenant scope
- conversation_id (nullable)
- title
- description
- original_request
- task_type
- status
- priority
- current_step
- total_steps
- plan_json
- result_summary
- created_at
- updated_at
- started_at
- completed_at
- failed_at

Suggested statuses:

```text id="mavqod"
draft
planned
awaiting_confirmation
running
paused
completed
failed
cancelled
```

---

## AI Task Steps

Logical fields:

- id
- task_id
- step_number
- title
- description
- step_type
- tool_id / action_id
- input_json
- output_json
- status
- requires_confirmation
- started_at
- completed_at
- error_message
- created_at
- updated_at

Suggested step types:

```text id="bl7o4c"
read
action
reasoning
```

Suggested statuses:

```text id="yupxtl"
pending
running
awaiting_confirmation
completed
failed
skipped
cancelled
```

---

## AI Automations

Logical fields:

- id
- name
- description
- company_id / tenant scope
- created_by
- is_active
- trigger_type
- trigger_config_json
- workflow_json
- last_run_at
- next_run_at
- created_at
- updated_at

Do not implement unrestricted background execution yet.

---

## AI Automation Runs

Logical fields:

- id
- automation_id
- status
- started_at
- completed_at
- result_json
- error_message
- triggered_by
- created_at

Use indexes and foreign keys consistent with the ERP architecture.

---

# 3. AI Task Planner

Create a reusable Task Planner service.

The planner receives:

- User objective
- ERP context
- Available tools
- Available actions
- User permissions

The planner produces a structured plan.

The plan must not contain arbitrary executable code.

Each step must reference:

- A registered tool
- A registered action
- Or a controlled reasoning step

Example conceptual structure:

```json id="y5b3hd"
{
    "title": "Review pending purchase requisitions",
    "steps": [
        {
            "step": 1,
            "type": "read",
            "tool_id": "procurement.search_purchase_requisitions",
            "purpose": "Find pending requisitions"
        },
        {
            "step": 2,
            "type": "reasoning",
            "purpose": "Summarize results"
        }
    ]
}
```

The backend must validate every generated plan.

AI-generated plans are proposals, not trusted executable instructions.

---

# 4. Task Plan Validation

Before saving or executing a plan:

Validate:

- Step count limit
- Valid step sequence
- Registered tool/action IDs
- User permissions
- Module availability
- Input schema
- No recursive execution
- No arbitrary URLs
- No arbitrary code
- No unregistered operations

Reject invalid plans.

The server-side registry remains authoritative.

---

# 5. Task Planning UI

Add a Task interface at:

**AI → Tasks**

Users should be able to:

- Create task from natural language
- View proposed plan
- Review steps
- Start task
- Cancel task
- View progress
- View results
- View history

Example:

```text id="9ygscm"
Task: Review Pending Purchase Requisitions

✓ Step 1: Find pending requisitions
→ Step 2: Analyze results
○ Step 3: Prepare summary
```

Use existing ERP UI patterns.

---

# 6. Task Creation From AI Assistant

Allow the Assistant to suggest:

```text id="uvsf8g"
[Create Task]
```

when a request clearly requires multiple operations.

Clicking should create a task draft.

Do not automatically convert every conversation into a task.

The user must initiate task creation.

---

# 7. Task Execution Engine

Create a controlled task executor.

Execution rules:

1. Load task server-side.
2. Verify ownership/scope.
3. Validate task status.
4. Load current step.
5. Validate step.
6. Execute only registered operation.
7. Store normalized result.
8. Update progress.
9. Continue to next allowed step.

Do not execute arbitrary instructions from the stored plan.

Every step must be revalidated before execution.

---

# 8. Read-Only Task Steps

Read-only steps may execute after the user explicitly starts the task.

Example:

```text id="pqjfdc"
Search Purchase Orders
        ↓
Analyze Results
        ↓
Generate Summary
```

All existing AI F8 limits still apply:

- Permission checks
- Tenant scope
- Result limits
- Tool call limits

---

# 9. Action Steps

If a task reaches an action step:

- Pause task.
- Set task status to:

```text id="x4upke"
awaiting_confirmation
```

- Generate action preview.
- Use AI F9 pending-action infrastructure.
- Require explicit user confirmation.

After successful execution:

- Resume task.
- Continue next steps.

Do not auto-confirm actions.

---

# 10. Task Progress Tracking

Display:

- Overall progress
- Current step
- Completed steps
- Pending steps
- Paused confirmation state
- Errors

Example:

```text id="f7m2ft"
Task Progress: 2 of 4 steps complete

✓ Find pending requisitions
✓ Collect supplier information
⏳ Waiting for confirmation
○ Generate final report
```

Progress must originate from actual execution state, not AI-generated claims.

---

# 11. Task Cancellation

Allow users to cancel their own running/pending tasks.

Rules:

- Stop future steps.
- Do not roll back already completed actions automatically.
- Mark remaining steps cancelled/skipped appropriately.
- If an action is pending confirmation, invalidate/cancel it.
- Log cancellation.

Do not attempt unsafe automatic rollback.

---

# 12. Task Failure Handling

If a step fails:

- Stop task by default.
- Mark step failed.
- Store safe error details.
- Mark task failed.

Do not blindly continue after failed critical steps.

Future recovery/retry logic can be added later.

---

# 13. Automation Definitions

Create the architecture for reusable automations.

Users should be able to define an automation conceptually containing:

- Name
- Description
- Trigger
- Workflow

For this phase, support trigger types structurally:

```text id="csgh3w"
manual
scheduled
event
```

But implement actual execution only for:

```text id="zt0lq7"
manual
```

Scheduled and event triggers should be stored/configurable only if cleanly supported.

Do not build cron/background worker execution yet.

---

# 14. Manual Automation Run

A user can manually run an automation.

Execution should create an:

```text id="6isjs7"
AI Automation Run
```

which uses the controlled task execution engine.

All permissions and confirmation rules apply.

Automation definitions must not bypass:

- Tool permissions
- Action confirmation
- Tenant scope
- Execution limits

---

# 15. Automation Workflow Validation

Automation workflow steps must reference:

- Registered read-only tools
- Registered actions
- Controlled reasoning steps

Do not allow:

- PHP code
- SQL
- Shell commands
- Arbitrary HTTP requests
- Unregistered functions

Validate workflows when:

- Created
- Edited
- Executed

---

# 16. Automation UI

Create:

**AI → Automation**

with:

- Automation list
- Create automation
- Edit automation
- Activate/deactivate
- Manual run
- Run history

Keep workflow creation structured.

Do not build a complex visual drag-and-drop workflow builder unless the existing ERP architecture already supports it.

A simple step-based UI is sufficient.

---

# 17. Permissions

Integrate with existing AI permission architecture.

Use existing permissions if already created.

If permissions for Tasks/Automation were defined in AI F1, use those.

Otherwise add only necessary permissions following existing naming conventions.

Logical capabilities:

```text id="wkwzfr"
ai.tasks.view
ai.tasks.create
ai.tasks.run
ai.tasks.cancel

ai.automation.view
ai.automation.create
ai.automation.update
ai.automation.run
```

Server-side enforcement is mandatory.

Do not allow automation ownership to bypass permissions.

---

# 18. Task and Automation Audit Logging

Log:

### Tasks

- Created
- Planned
- Started
- Step started
- Step completed
- Paused
- Cancelled
- Failed
- Completed

### Automations

- Created
- Updated
- Activated
- Deactivated
- Manually run
- Run completed
- Run failed

Do not log secrets.

Do not duplicate full sensitive ERP data unnecessarily.

---

# 19. Execution Limits

Implement configurable safeguards:

- Maximum steps per task
- Maximum tool calls
- Maximum execution time
- Maximum concurrent tasks per user
- Maximum active pending actions
- Maximum automation runs

Prevent:

- Infinite loops
- Recursive task creation
- Automation calling itself indefinitely

---

# 20. Security Requirements

Mandatory:

- Plans validated server-side.
- Only registered tools/actions execute.
- Permissions checked at planning and execution.
- Tenant/company scope enforced.
- Write actions require AI F9 confirmation.
- No arbitrary code execution.
- No arbitrary SQL.
- No arbitrary shell commands.
- No arbitrary external URLs.
- Cancellation cannot undo completed operations automatically.
- Task status changes are server-authoritative.
- Execution logs do not expose secrets.

---

# Important Constraints

Do NOT implement yet:

- Fully autonomous unattended write operations
- Scheduled background execution
- Event-driven execution
- Self-modifying workflows
- Recursive agents
- Unlimited task loops
- Shell/system command execution
- Arbitrary HTTP automation

This step is:

**Task Planning + Controlled Multi-Step Execution + Manual Automation Foundation**

---

# Completion Criteria

AI F10 is complete when:

- Users can create tasks.
- AI can propose structured task plans.
- Plans are server-side validated.
- Users can review and start tasks.
- Read-only multi-step tasks execute.
- Action steps pause for confirmation.
- Progress is tracked accurately.
- Tasks can be cancelled.
- Failures are handled safely.
- Automation definitions can be created.
- Manual automation runs work.
- No unattended autonomous write action occurs.
- No scheduled/event background execution occurs yet.

After implementation, provide:

- Files created
- Files modified
- Database changes
- Task architecture
- Automation architecture
- Permissions
- Security controls
- Known limitations