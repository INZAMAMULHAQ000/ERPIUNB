Implement **AI F12 — AI Agents & Controlled Autonomous Execution** in the existing **Core PHP + MySQL ERP**.

### Objective

Build the first true AI Agent architecture.

An AI Agent should be able to pursue a defined objective using multiple approved steps, tools, reasoning, and task execution while remaining strictly controlled by:

- Permissions
- Tool registry
- Action registry
- Tenant/company scope
- Execution limits
- Human confirmation rules
- Audit logging

This phase introduces controlled autonomous execution for **read-only and reasoning operations**.

Any ERP state-changing action must still use the **AI F9 mandatory human confirmation framework**.

---

# 1. Create AI Agent Architecture

Create a reusable AI Agent framework.

Conceptual flow:

```text
User Objective
      ↓
Agent Selection
      ↓
Agent Configuration
      ↓
Planning
      ↓
Observe Current State
      ↓
Select Approved Tool
      ↓
Execute Tool
      ↓
Evaluate Result
      ↓
Continue / Finish / Pause
      ↓
Final Result
```

The agent must never have unrestricted access to:

- Database
- SQL
- PHP functions
- Filesystem
- Shell
- Arbitrary HTTP endpoints

Every capability must pass through existing controlled systems.

Architecture:

```text
AI Agent
   ├── AI F6 Context Engine
   ├── AI F7 Navigation
   ├── AI F8 Read Tools
   ├── AI F9 Confirmed Actions
   ├── AI F10 Tasks
   └── AI F11 Knowledge/RAG
```

The Agent is an orchestration layer, not a bypass layer.

---

# 2. Agent Registry

Create a centralized Agent Registry.

Each agent definition should contain:

- Agent ID
- Name
- Description
- Category
- Module scope
- Enabled status
- Required permissions
- Allowed tools
- Allowed actions
- Allowed knowledge collections/scopes
- Maximum execution steps
- Maximum tool calls
- Maximum runtime
- Risk level
- System instructions
- Human confirmation policy

Conceptual example:

```php
[
    'id' => 'procurement_assistant',
    'name' => 'Procurement Assistant',
    'description' => 'Helps analyze procurement information and complete approved procurement tasks.',
    'module' => 'procurement',

    'allowed_tools' => [
        'procurement.search_suppliers',
        'procurement.search_purchase_requisitions',
        'procurement.search_purchase_orders'
    ],

    'allowed_actions' => [
        'procurement.create_supplier'
    ],

    'max_steps' => 10,
    'max_runtime_seconds' => 60,
    'risk_level' => 'controlled'
]
```

The registry is authoritative.

AI-generated agents must not dynamically grant themselves additional capabilities.

---

# 3. Agent Contract

Create a reusable Core PHP Agent contract/interface.

Logical lifecycle:

```text
getDefinition()
canRun()
buildContext()
createPlan()
selectNextStep()
executeStep()
evaluateStepResult()
shouldContinue()
complete()
fail()
pause()
```

Keep implementation practical.

Do not over-engineer with unnecessary framework abstractions.

Every agent execution must be traceable.

---

# 4. Agent Types

Create architecture supporting future types:

```text
assistant
task_agent
monitoring_agent
automation_agent
specialist_agent
```

For AI F12, implement:

### Specialist Agent

A domain-specific agent with controlled capabilities.

### General Task Agent

A generic agent capable of orchestrating approved tools for a user-defined objective.

Do not implement self-replicating agents.

Do not allow agents to create other agents automatically.

---

# 5. Initial Agents

Implement only useful agents based on currently available ERP functionality.

## A. ERP General Assistant Agent

Capabilities:

- Understand ERP context
- Navigate
- Use authorized read-only tools
- Search authorized knowledge
- Perform multi-step analysis

Examples:

> Find pending purchase requisitions and summarize the important issues.

> Check recent purchase orders and tell me which suppliers appear most frequently.

This agent may autonomously perform multiple **read-only** steps within configured limits.

---

## B. Procurement Specialist Agent

Capabilities should be restricted to Procurement.

Allowed:

- Supplier lookup
- Purchase requisition search
- Purchase order search
- Procurement knowledge retrieval
- Procurement navigation
- Analysis and summaries

Potential user request:

> Review all pending purchase requisitions and give me a procurement summary.

The agent can:

```text
1. Search requisitions
2. Analyze statuses
3. Identify patterns
4. Search relevant Procurement SOP if useful
5. Generate summary
```

All operations remain read-only unless a confirmed action is explicitly required.

---

# 6. Agent Planning

Agents may create internal structured plans.

Example:

```json
{
    "objective": "Review pending purchase requisitions",
    "steps": [
        {
            "type": "tool",
            "tool_id": "procurement.search_purchase_requisitions",
            "purpose": "Find pending requisitions"
        },
        {
            "type": "reasoning",
            "purpose": "Identify important patterns"
        },
        {
            "type": "knowledge",
            "tool_id": "knowledge.search",
            "purpose": "Check relevant approval policy"
        }
    ]
}
```

The backend must validate:

- Every tool
- Every action
- Every step type
- Input schema
- Agent capability scope
- User permissions

Do not execute raw AI-generated plans.

---

# 7. Dynamic Agent Loop

Implement a controlled execution loop.

Conceptual lifecycle:

```text
START
  ↓
Build Agent Context
  ↓
Determine Next Step
  ↓
Validate Step
  ↓
Execute Approved Operation
  ↓
Store Observation
  ↓
Evaluate Progress
  ↓
Continue?
 ├── Yes → Next Step
 ├── Needs Confirmation → Pause
 ├── Limit Reached → Stop Safely
 └── Objective Complete → Finish
```

The agent must have hard limits.

Never allow:

```text
while(true)
```

or unbounded autonomous loops.

---

# 8. Agent Memory

Create controlled execution memory.

Separate:

### Conversation Memory

Existing AI conversation history.

### Agent Working Memory

Temporary execution state such as:

- Objective
- Current plan
- Completed steps
- Tool observations
- Important intermediate findings

Do not blindly inject unlimited working memory into prompts.

Implement limits for:

- Maximum memory entries
- Maximum character/token size
- Maximum tool output retained

Sensitive data must not be unnecessarily retained.

---

# 9. Agent Execution Records

Create database structures.

## AI Agents

If persistent configurable agents are supported:

- id
- agent_type
- name
- description
- module
- configuration_json
- enabled
- created_by
- company_id
- created_at
- updated_at

System agents may alternatively remain registry-defined.

Do not require user-created custom agents yet.

---

## AI Agent Runs

Create:

- id
- agent_id
- user_id
- company_id
- conversation_id
- objective
- status
- current_step
- total_steps
- started_at
- completed_at
- failed_at
- result_summary
- metadata_json

Suggested statuses:

```text
queued
planning
running
awaiting_confirmation
paused
completed
failed
cancelled
limit_reached
```

---

## AI Agent Steps

Create:

- id
- agent_run_id
- step_number
- step_type
- operation_id
- input_json
- observation_json
- status
- started_at
- completed_at
- duration_ms
- error_message

Do not store unnecessary raw secrets or unrestricted sensitive data.

---

# 10. Agent Execution UI

Create:

**AI → Agents**

Features:

- Available Agents
- Agent description
- Allowed capability summary
- Start Agent
- Running agents
- Agent run history
- Step timeline
- Final result
- Cancel agent run

Example:

```text
Procurement Specialist

Status: Running

✓ Step 1 — Search pending requisitions
✓ Step 2 — Analyze supplier distribution
⏳ Step 3 — Checking Procurement SOP
○ Step 4 — Preparing summary
```

Use existing ERP UI design.

Do not expose raw chain-of-thought or private model reasoning.

Show only useful execution summaries.

---

# 11. User-Facing Agent Activity

Users should understand what the agent is doing.

Show high-level activity messages such as:

```text
Searching pending purchase requisitions...
Analyzing procurement patterns...
Checking relevant company procedures...
Preparing your summary...
```

Do NOT display:

- Hidden prompts
- Chain-of-thought
- Raw internal reasoning
- API credentials
- Raw SQL

The UI should show action summaries, not private reasoning traces.

---

# 12. Agent Capability Boundaries

Every agent must have explicit capability boundaries.

Example:

```text
Procurement Specialist Agent

Allowed:
✓ Search Procurement data
✓ Read Procurement Knowledge
✓ Analyze results

Not Allowed:
✗ Access Finance records
✗ Modify records without confirmation
✗ Access arbitrary databases
✗ Browse arbitrary websites
```

Capability boundaries must be enforced server-side.

They are not just descriptive UI.

---

# 13. Agent Permissions

Agent execution requires:

1. User permission to use the agent.
2. Agent enabled status.
3. User access to underlying tools.
4. Agent authorization for those tools.
5. Tenant/company scope validation.

Effective permissions are the intersection of:

```text
User Permissions
        ∩
Agent Allowed Capabilities
        ∩
Module Availability
```

An agent must never elevate a user's access.

---

# 14. Agent Actions

Agents may encounter a state-changing operation.

Example:

> Review pending requisitions and create a supplier if ABC Trading does not exist.

Flow:

```text
Agent searches suppliers
        ↓
Agent determines supplier missing
        ↓
Agent proposes create_supplier action
        ↓
AI F9 Action Preview
        ↓
TASK/AGENT PAUSES
        ↓
USER CONFIRMS
        ↓
Action Executes
        ↓
Agent resumes
```

Mandatory rules:

- Agent cannot auto-confirm.
- Agent cannot silently execute writes.
- Every action uses the existing Pending Action system.
- Permissions are revalidated before execution.

---

# 15. Agent Pause and Resume

Support:

```text
running
↓
awaiting_confirmation
↓
user confirms/cancels
↓
resume or terminate
```

Agent state must persist server-side.

Do not depend on browser memory.

A user should be able to return later and view the run state, subject to expiration/retention rules.

---

# 16. Agent Cancellation

Allow authorized users to cancel an agent run.

Rules:

- Stop future execution.
- Mark run cancelled.
- Invalidate pending confirmation where applicable.
- Do not undo completed operations automatically.
- Log cancellation.

Prevent race conditions between cancellation and the next step.

---

# 17. Agent Limits

Implement configurable limits.

At minimum:

- Maximum steps per run
- Maximum tool calls
- Maximum reasoning cycles
- Maximum runtime
- Maximum working memory size
- Maximum concurrent runs per user
- Maximum nested execution depth

When a limit is reached:

```text
Status: limit_reached
```

The agent must stop safely and explain that it could not continue within configured limits.

---

# 18. Loop and Recursion Protection

Explicitly prevent:

- Agent calling itself
- Agent creating another agent
- Recursive task-agent loops
- Automation recursively launching agents
- Unlimited tool retries

Maintain execution lineage/depth metadata.

Reject nested execution beyond configured depth.

---

# 19. Error Handling

Handle:

- Tool failure
- Permission changes
- Provider failure
- Timeout
- Invalid agent plan
- Missing record
- Knowledge retrieval failure
- Action confirmation timeout
- Execution limit

Do not expose raw stack traces.

Store safe technical diagnostics for administrators where appropriate.

The user should receive understandable status such as:

> I couldn't complete the procurement review because the purchase requisition search became unavailable.

Do not fabricate completion.

---

# 20. Agent Result

When complete:

- Generate final summary.
- Clearly distinguish findings from actions performed.
- Include relevant source attribution where knowledge was used.
- Include record references where authorized.
- Include navigation links where useful.

Example:

```text
Procurement Review Complete

• 18 pending purchase requisitions found.
• 6 have been pending for more than 7 days.
• 3 suppliers account for most open orders.

No records were modified.

Sources used:
• Purchase Requisition data
• Procurement SOP
```

All claims must be based on actual tool results.

---

# 21. Agent Audit Logging

Log:

- Agent started
- Planning completed
- Step started
- Tool requested
- Tool completed
- Action proposed
- Confirmation requested
- Agent paused
- Agent resumed
- Agent completed
- Agent failed
- Agent cancelled
- Limit reached

Include:

- Agent ID
- Agent run ID
- User
- Company scope
- Timestamp
- Duration
- Status

Do not log:

- Secrets
- API keys
- Hidden chain-of-thought
- Full sensitive data unnecessarily

---

# 22. Agent Configuration

Create administrator configuration for agent safety limits.

Suggested settings:

```text
Default maximum agent steps
Maximum tool calls per run
Maximum runtime
Maximum concurrent runs
Maximum nested depth
Maximum working memory size
Allow write-action proposals
Agent confirmation timeout
```

Configuration must be server-side.

Do not allow users to override hard safety limits from the browser.

---

# 23. Provider Compatibility

Agents require an AI model capable of reliable multi-step tool use.

Integrate with existing Model Management.

Add/extend model capabilities where appropriate:

```text
chat
tool_calling
structured_output
agent_capable
```

An agent should not start with a model lacking required capabilities.

Gracefully inform users if no compatible model is configured.

Do not use unsafe free-text parsing as a substitute for reliable tool calls.

---

# 24. Security Requirements

Mandatory:

- Agents use only registered capabilities.
- Agents cannot execute SQL directly.
- Agents cannot execute arbitrary PHP.
- Agents cannot access arbitrary files.
- Agents cannot access arbitrary URLs.
- User permissions always apply.
- Agent capability restrictions always apply.
- Tenant/company scope always applies.
- Write actions require explicit confirmation.
- Execution limits are enforced server-side.
- Recursion is prevented.
- Working memory is size-limited.
- Agent state is server-authoritative.
- No hidden reasoning/chain-of-thought is exposed.
- All important activity is auditable.

---

# Important Constraints

Do NOT implement:

- Fully unrestricted autonomous agents
- Self-improving agents
- Self-replicating agents
- Agents creating new tools
- Agents creating other agents autonomously
- Arbitrary web browsing
- Shell/system execution
- Direct SQL generation
- Unattended write execution
- Automatic approval/payment/financial posting

This step is:

**Controlled AI Agents using Existing Safe Tools, Tasks, Knowledge, and Human Confirmation.**

---

# Completion Criteria

AI F12 is complete when:

- Agent Registry exists.
- At least General Assistant Agent and Procurement Specialist Agent exist.
- Agent capabilities are explicitly restricted.
- Agents can perform controlled multi-step read-only work.
- Agent working memory is controlled.
- Agent runs persist.
- Agent progress is visible.
- Agent actions pause for human confirmation.
- Agents can resume after confirmed actions.
- Limits stop runaway execution.
- Recursion is prevented.
- Agent results are grounded in real tool/knowledge results.
- Agent activity is audited.
- No autonomous write occurs without explicit confirmation.

After implementation, provide:

- Files created
- Files modified
- Database changes
- Agent architecture
- Agents implemented
- Capability mappings
- Execution limits
- Permission model
- Security controls
- Known limitations