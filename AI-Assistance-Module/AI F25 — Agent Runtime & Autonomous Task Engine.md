# AI F25 — Agent Runtime & Autonomous Task Engine

Build **AI F25 — Agent Runtime & Autonomous Task Engine** for the existing ERP.

## NON-NEGOTIABLE TECH STACK

- **Core PHP + MySQL only**
- **No frameworks**
- Reuse the ERP's existing architecture, authentication, RBAC, routing, UI components, database conventions, logging, and APIs.
- Do not introduce Laravel, Symfony, CodeIgniter, React, Vue, Node.js, Python services, or another application framework.
- Do not duplicate functionality already implemented in previous AI features.
- Integrate with the existing AI features, especially **F9, F11, F13, F16, F17, F18, F22, F23, and F24**.

## OBJECTIVE

Create the controlled runtime that turns a user's natural-language request into a **managed multi-step AI task**.

Example:

> "Check overdue purchase orders, identify the suppliers affected, prepare follow-up emails, and show me what will be sent."

The system should be able to:

**Understand → Plan → Govern → Execute → Verify → Continue/Pause → Complete → Report**

The agent must never become an unrestricted shell, SQL, HTTP, PHP, or database executor.

---

## 1. AGENT CENTER

Add:

**AI → Agents**

Provide:

- Agents
- Agent Templates
- Tasks
- Active Runs
- Approvals
- Checkpoints
- Task History
- Failed Tasks
- Agent Policies

Agent definition should support:

- name
- description
- purpose
- enabled/disabled
- allowed skills
- allowed tools
- allowed integrations
- allowed providers/models
- autonomy level
- maximum steps
- maximum runtime
- maximum tool calls
- maximum cost
- maximum records affected
- retry limit
- approval requirements
- allowed departments/roles/users
- task memory policy

---

## 2. AGENT TASK CREATION

Allow users to create an agent task from:

- AI Command Center
- AI chat
- dashboard action
- workflow
- scheduler
- approved integration event

Store:

- task ID
- company/tenant
- requesting user
- original request
- normalized intent
- agent
- status
- priority
- created time
- started time
- completed time
- current step
- progress
- result
- error
- cancellation state

Statuses should include:

- pending
- planning
- awaiting_confirmation
- awaiting_review
- running
- paused
- failed
- cancelled
- completed
- partially_completed

---

## 3. PLAN → EXECUTE ARCHITECTURE

Implement a controlled agent loop:

1. Receive request.
2. Authenticate user.
3. Validate tenant.
4. Load agent configuration.
5. Load relevant context.
6. Generate structured execution plan.
7. Validate the plan.
8. Run every planned action through F24 governance.
9. Execute only registered ERP tools/skills/integrations.
10. Capture result.
11. Validate result.
12. Decide whether another step is required.
13. Repeat within limits.
14. Complete or request human intervention.
15. Produce a final user-facing summary.

The LLM must **never directly execute an action**.

It can only propose structured tool calls that the server validates.

---

## 4. STRUCTURED AGENT PLANS

Agent plans must use a strict server-validated structure.

Each step should contain:

- step ID
- sequence
- objective
- tool/skill
- parameters
- expected result
- dependency
- risk
- status
- retry count
- execution result
- verification result

Reject malformed or unauthorized plans.

Do not allow the model to dynamically create arbitrary tools.

---

## 5. TOOL EXECUTION

Agents may use only registered capabilities from existing systems:

- ERP tools
- AI skills
- workflows
- knowledge retrieval
- approved integrations
- approved external connectors

Every execution must pass through F24:

**Authentication → Tenant → Permission → Data Policy → Risk → Tool Policy → Provider Policy → Autonomy → Budget → Confirmation/Review → Execute → Audit**

Do not create a second security mechanism that bypasses F24.

---

## 6. AUTONOMY MODES

Support:

- Assist
- Suggest
- Confirm
- Supervised
- Restricted

Respect the user's and organization's F24 autonomy policy.

Examples:

### Low-risk
Agent may automatically read approved ERP data.

### Medium-risk
Agent may prepare an action but require confirmation.

### High-risk
Agent must require explicit confirmation or human review.

### Critical
Agent must be denied unless specifically authorized by governance policy.

The agent must never elevate its own autonomy.

---

## 7. PLAN PREVIEW / DRY RUN

Before execution, support:

**Preview Plan**

Show:

- what the agent intends to do
- steps
- tools
- records/categories affected
- estimated impact
- estimated cost
- risk level
- external systems involved
- actions requiring approval

Provide:

- Execute
- Modify Request
- Cancel

Dry-run mode must not modify production data.

---

## 8. CHECKPOINTS

Implement durable checkpoints.

Checkpoint after:

- significant actions
- approval requirements
- long-running operations
- configurable number of steps
- external writes
- high-risk operations

Store enough state to safely resume.

The agent must not restart from step 1 after a recoverable interruption.

---

## 9. PAUSE / RESUME / CANCEL

Users with permission must be able to:

- pause
- resume
- cancel
- retry failed step
- restart task
- request human takeover

Cancellation must prevent queued future actions.

Already-completed actions must remain recorded.

---

## 10. APPROVALS & HUMAN HANDOFF

When governance requires confirmation/review:

Create an approval record containing:

- task
- step
- requesting user
- proposed action
- parameters
- affected records
- risk
- expiration
- approver
- decision
- timestamp

Approval must be cryptographically/uniquely bound to the exact action parameters.

If parameters change, approval becomes invalid.

Support:

- approve
- reject
- request changes
- delegate where existing RBAC permits

Never allow the agent to approve its own action.

---

## 11. MEMORY & TASK CONTEXT

Implement controlled agent context.

Separate:

- system instructions
- agent instructions
- user request
- ERP data
- retrieved knowledge
- tool results
- previous steps
- approval decisions

Treat retrieved/external content as **untrusted data**, not instructions.

Task memory must respect F24:

- tenant
- permissions
- data classification
- retention
- masking
- privacy

Do not expose secrets or credentials to the model.

---

## 12. LOOP & FAILURE PROTECTION

Implement:

- maximum steps
- maximum runtime
- maximum tool calls
- maximum retries
- maximum cost
- duplicate-action detection
- repeated-step detection
- circular dependency detection
- stuck-task detection
- exponential retry/backoff where appropriate

If limits are reached:

**pause/stop safely and request human intervention.**

Never let an agent run indefinitely.

---

## 13. IDEMPOTENCY & SAFE RETRIES

For write operations, implement idempotency where possible.

A retry must not accidentally:

- create duplicate invoices
- create duplicate purchase orders
- send duplicate emails
- create duplicate payments
- create duplicate records
- repeat destructive operations

Store execution identifiers and results.

If safe retry cannot be guaranteed, require review instead.

---

## 14. RESULT VERIFICATION

After each important action, verify the result.

Examples:

- record was actually created
- expected status changed
- email was actually queued
- workflow completed
- integration returned success
- affected record count matches expectation

Do not report an action as successful merely because the model predicted success.

---

## 15. LONG-RUNNING TASKS

Support tasks that take longer than a normal chat request.

Implement:

- background execution queue using the existing Core PHP architecture
- task polling/status
- checkpoints
- resumability
- timeout handling
- progress updates
- notification when completed/failed/awaiting approval

Do not require the browser session to remain open.

Use the existing scheduler/cron architecture where available.

---

## 16. USER PROGRESS UI

In AI Command Center show an active task such as:

**Checking overdue purchase orders...**

Then:

- ✓ Retrieved purchase orders
- ✓ Identified overdue suppliers
- ✓ Prepared follow-up messages
- ⚠ Waiting for approval
- ○ Send messages
- ○ Verify delivery

Show:

- current step
- progress
- elapsed time
- pending approval
- errors
- pause/cancel controls

Do not expose unnecessary internal prompts or security information.

---

## 17. AGENT RESULT

When completed, provide a concise result:

- what was requested
- what was actually done
- records affected
- actions skipped
- failures
- approvals obtained
- external actions
- important warnings

Clearly distinguish:

**Completed / Partially Completed / Failed / Awaiting Action**

Never claim success without server-side evidence.

---

## 18. OBSERVABILITY INTEGRATION

Integrate every agent run with F23.

Record:

- task
- run
- step
- model/provider
- latency
- token/cost information where available
- tool calls
- governance decisions
- failures
- retries
- approvals
- final outcome

Create a trace showing:

**Request → Plan → Governance → Tool → Result → Verification → Next Step**

Do not log secrets or unrestricted sensitive data.

---

## 19. GOVERNANCE INTEGRATION

Every agent step must use F24.

Specifically enforce:

- permissions
- tenant isolation
- data policies
- provider restrictions
- tool restrictions
- risk rules
- autonomy limits
- financial thresholds
- bulk-action limits
- external communication rules
- confirmation
- human review
- budget limits
- emergency kill switches

If F24 denies a step, the agent must not attempt an alternative bypass.

---

## 20. WORKFLOW / SCHEDULER INTEGRATION

Allow approved agents to be triggered by:

- workflows
- scheduled jobs
- approved system events
- integrations/webhooks

Scheduled agents must still execute under the same user/company/service-account governance rules.

Do not create a hidden privileged execution identity.

---

## 21. DATABASE DESIGN

Create normalized tables following existing ERP conventions.

At minimum support:

- agents
- agent_tasks
- agent_runs
- agent_steps
- agent_checkpoints
- agent_approvals
- agent_context
- agent_artifacts
- agent_events

Include:

- tenant/company ID
- ownership
- timestamps
- status
- indexes
- foreign keys where appropriate
- audit fields

Use prepared statements everywhere.

---

## 22. ARTIFACTS

Allow an agent to produce controlled artifacts:

- reports
- drafts
- summaries
- structured results
- generated documents through approved ERP functionality

Store artifacts with:

- owner
- tenant
- task
- classification
- permissions
- retention
- creation source

---

## 23. SECURITY HARD BOUNDARIES

The agent must NEVER be able to:

- execute arbitrary SQL
- execute shell commands
- execute arbitrary PHP
- access filesystem paths outside approved services
- call arbitrary URLs
- obtain API keys/passwords/tokens
- bypass RBAC
- bypass F24
- switch tenants
- impersonate another user
- grant itself permissions
- approve itself
- disable governance
- disable audit logging
- modify security policies
- bypass confirmation/review
- hide failed actions
- silently repeat destructive actions

---

## 24. ADMINISTRATION

Provide administrators with:

- agent enable/disable
- allowed tools
- autonomy level
- limits
- approval rules
- provider/model restrictions
- cost limits
- department/user assignment
- execution history
- failure history

Changes must be permission-protected and audited.

---

## 25. IMPLEMENTATION QUALITY

Before finishing:

- inspect existing AI architecture first
- reuse existing services and tables where appropriate
- do not duplicate F24 governance
- do not duplicate F23 tracing
- use existing authentication/RBAC
- use existing UI patterns
- use transactions for multi-record state changes
- handle concurrency safely
- prevent duplicate execution
- validate all model-generated structured data server-side
- maintain tenant isolation everywhere
- preserve backward compatibility

Add useful comments around the agent orchestration/security boundaries.

---

# COMPLETION CRITERIA

F25 is complete only when a user can issue a natural-language multi-step request, the system can create a structured plan, execute approved registered tools, pause for approval when required, safely resume, verify results, handle failures, show progress, and produce an accurate final result — while every action remains subject to F24 governance and F23 observability.

Do not weaken existing security controls to make the agent work.