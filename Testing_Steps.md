# Testing Steps

## 1. Create Basic Workflow

Create:

Manual Trigger
↓
Notification

Run manually.

Expected:

Workflow starts.
Notification created.
Run marked completed.

## 2. Event Trigger

Configure:

Purchase Requisition Updated
↓
Condition: Status = Pending
↓
Notification

Update a matching requisition.

Expected:

Only matching event triggers workflow.

## 3. Condition Test

Test values:

Pending
Approved
Rejected

Expected:

Only configured condition path executes.

## 4. AI Workflow Generation

Ask:

When a requisition is overdue, notify procurement manager and create a task.

Expected:

Workflow draft generated.
Nodes are registered.
Draft opens in editor.
Workflow remains disabled.

## 5. Dry-Run Test

Test workflow using sample context.

Expected:

Execution preview appears.
No real ERP records created.
No real notifications sent unless explicitly test-safe.

## 6. Approval Node

Create:

Manual
↓
Human Approval
↓
Action

Expected:

Action waits for approval.
Reject ends workflow.
Approve continues.

## 7. Delay Node

Create:

Manual
↓
Wait 5 Minutes
↓
Notification

Expected:

Workflow enters waiting state.
PHP request ends.
Scheduler resumes workflow later.

## 8. Versioning

Enable Workflow v1.

Create v2.

Expected:

v1 runs remain tied to v1.
v2 can be tested separately.
Enabling v2 does not mutate old runs.

## 9. Loop Prevention

Attempt circular graph.

Expected:

Validation blocks publishing.

## 10. Permission Test

Create workflow using restricted action.

Run as unauthorized user.

Expected:

Action does not execute.
Permission failure safely logged.

## 11. Tenant Isolation

Create workflow in Company A.

Access Company B.

Expected:

Company B cannot access or execute Company A workflow.

## 12. Retry Test

Force temporary AI provider timeout.

Expected:

Safe retry available according to policy.

Force partial write action.

Expected:

System does not blindly retry.

## 13. Kill Switch Test

Disable Workflow Execution.

Trigger enabled workflow.

Expected:

Workflow does not execute.
Safe status/log appears.

## 14. Monitoring Test

Force a workflow failure.

Expected:

Appears in Workflow Runs.
Failed node identified.
Sanitized diagnostic available.

## 15. Regression Test

Verify integration with:

F9 Actions
F10 Tasks
F11 Knowledge
F12 Agents
F13 Scheduler
F14 Insights
F15 Command Center
F16 Management
F17 Skills

Expected:

Workflow Studio orchestrates existing systems without bypassing safety controls.