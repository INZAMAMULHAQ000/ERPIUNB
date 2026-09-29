Implement **AI F15 — Universal AI Command Center & Natural Language ERP Control** in the existing **Core PHP + MySQL ERP**.

### Objective

Create the main “living GPT inside the ERP” experience: a universal AI interface where non-technical users can ask, instruct, navigate, analyze, and initiate ERP work in natural language.

The AI Command Center should become the primary intelligent entry point to the ERP.

Examples:

- “Show me pending purchase requisitions.”
- “Why are there so many pending requisitions?”
- “Find ABC Trading.”
- “Take me to supplier management.”
- “Summarize today's procurement activity.”
- “Create a task to review overdue requisitions.”
- “Check our procurement policy for approval limits.”
- “Review pending requisitions and tell me what needs attention.”

The system must intelligently route requests through existing AI capabilities rather than treating every message as a simple chat completion.

---

# 1. Command Center Architecture

Create a centralized AI Command Router.

Conceptual flow:

```text
User Input
    ↓
Conversation Context
    ↓
Intent Classification
    ↓
Capability Routing
    ↓
┌───────────────────────────────────┐
│ Navigation (F7)                   │
│ Read Tools (F8)                   │
│ Confirmed Actions (F9)            │
│ Tasks (F10)                       │
│ Knowledge / RAG (F11)             │
│ Agents (F12)                      │
│ Automation (F13)                  │
│ Insights (F14)                    │
└───────────────────────────────────┘
    ↓
Response Composer
    ↓
User
```

The Command Router must not bypass any existing security layer.

---

# 2. Intent Classification

Create a controlled intent classification layer.

Initial intent categories:

```text
conversation
question
navigation
data_lookup
analysis
knowledge_search
task_request
action_request
automation_request
agent_request
insight_request
multi_intent
unknown
```

Example:

> “Show me pending requisitions and explain why they are delayed.”

Could classify as:

```text
multi_intent
├── data_lookup
└── analysis
```

Do not trust raw model classification blindly.

Validate the selected capability server-side.

---

# 3. Capability Router

Build a centralized router mapping intents to existing AI systems.

Example:

```text
navigation
→ Navigation Service

data_lookup
→ Tool Registry

knowledge_search
→ Knowledge Tool

task_request
→ Task Engine

action_request
→ Pending Action System

automation_request
→ Automation Engine

agent_request
→ Agent Manager

insight_request
→ Insight Engine
```

The router should support multiple capabilities in one request.

Do not create duplicate implementations of existing features.

---

# 4. Universal Command Input

Create a persistent global AI command entry point.

Possible placements depending on existing UI:

- Top navigation
- Global command palette
- Floating AI button
- Dedicated AI page

Recommended UX:

```text
Ask ERP AI anything...
```

Keyboard shortcut may be added if existing UI supports it.

Examples shown to users:

```text
Search purchase requisitions
Take me to suppliers
Summarize procurement status
What does our procurement policy say?
Review pending requisitions
```

---

# 5. Command Center Page

Create:

**AI → Command Center**

Features:

- Natural language conversation
- Suggested actions
- Recent conversations
- Quick capabilities
- Current ERP context awareness
- Agent activity display
- Pending confirmations
- Relevant insights
- Navigation suggestions

The interface should feel like a professional AI workspace, not a developer console.

---

# 6. Context Awareness

The Command Center should understand current ERP context.

Example:

User is viewing:

```text
Procurement → Purchase Requisition #PR-1024
```

User asks:

> “Who created this?”

AI should resolve “this” using authorized page context.

Context may include:

- Current module
- Current page
- Current record type
- Current record ID
- Current filters
- Current company
- Current user permissions

Do not expose context the user cannot access.

Do not blindly trust client-supplied record IDs.

Validate server-side.

---

# 7. Context Priority

Use controlled context priority:

```text
1. Explicit user instruction
2. Current ERP page context
3. Conversation context
4. Relevant AI memory
5. General knowledge
```

Example:

> “Show me its supplier.”

“This/its” should resolve only when context is unambiguous.

If ambiguous:

Ask a concise clarification.

Do not guess sensitive record references.

---

# 8. Smart Request Decomposition

Support multi-part requests.

Example:

> “Find overdue requisitions, check the policy, and give me a summary.”

Router may decompose:

```text
1. procurement.search_requisitions
2. knowledge.search
3. analysis
4. response composition
```

Every generated step must pass capability validation.

For complex multi-step requests, the router may delegate to AI F12 Agent.

Do not create unbounded decomposition loops.

---

# 9. Automatic Agent Delegation

The Command Center may automatically suggest or delegate to an Agent when appropriate.

Example:

> “Review our entire procurement backlog and identify important problems.”

Response behavior:

```text
This requires a multi-step review.
Starting Procurement Specialist...
```

Delegation rules must be controlled.

Do not automatically start expensive agents for trivial questions.

Use criteria such as:

- Multi-step complexity
- Number of required tools
- Long-running analysis
- Cross-source investigation

Respect user permissions and agent availability.

---

# 10. Capability Transparency

Users should understand what the AI is doing.

Show concise activity indicators:

```text
Searching procurement records...
Checking company knowledge...
Reviewing relevant insights...
```

Do not expose:

- Chain-of-thought
- Hidden system prompts
- Raw model reasoning

Show capability-level summaries only.

---

# 11. Response Types

Support structured response types:

```text
answer
data_result
navigation
task_created
pending_confirmation
agent_run
automation
insight
clarification
error
```

Example navigation response:

```text
Supplier Management

Open Supplier Management →
```

Example data response:

```text
12 Pending Purchase Requisitions

• PR-1024 — 9 days pending
• PR-1029 — 7 days pending

View all →
```

---

# 12. Response Composer

Create a centralized response composition layer.

It receives validated outputs from:

- Tools
- Knowledge
- Agents
- Insights
- Tasks
- Navigation
- Actions

The composer must distinguish sources.

Example:

```text
ERP Data
12 pending requisitions found.

Company Policy
Requisitions above AED 10,000 require manager approval.

AI Analysis
Several older requisitions appear concentrated in one department.
```

Do not merge sources invisibly.

---

# 13. Rich ERP References

Support safe references in AI responses.

Examples:

```text
Purchase Requisition #PR-1024
Supplier: ABC Trading
View Supplier
```

References should use:

- Record type
- Record ID
- Display label
- Authorized route

Do not let the AI generate arbitrary URLs.

Validate links server-side.

---

# 14. Smart Navigation

Integrate AI F7 Navigation Intelligence.

Examples:

> “Take me to purchase orders.”

> “Open supplier management.”

> “Show procurement dashboard.”

Expected:

- Intent classified as navigation.
- Best matching authorized route found.
- User receives clickable navigation.

Do not expose inaccessible modules.

---

# 15. Universal Search

Add natural-language ERP search capability.

Example:

> “Find requisitions from last month above AED 5,000.”

The system should:

1. Identify entity type.
2. Map request to registered search tool.
3. Validate filters.
4. Execute authorized read-only search.
5. Return concise results.

Do not generate arbitrary SQL.

Do not allow unrestricted database search.

---

# 16. Guided Actions

For action requests:

> “Create a supplier called ABC Trading.”

Flow:

```text
Natural Language
    ↓
Intent = action_request
    ↓
Action Registry
    ↓
Structured Input Extraction
    ↓
Validation
    ↓
Preview
    ↓
User Confirmation
    ↓
Execute
```

All write operations continue using AI F9.

Never auto-execute writes.

---

# 17. Task Requests

Example:

> “Remind me to review overdue requisitions tomorrow.”

Route to AI F10 Tasks.

The AI should:

- Extract task details.
- Resolve date/time safely.
- Show resulting task.

Do not silently create tasks if existing task policy requires confirmation.

Respect existing user/task permissions.

---

# 18. Automation Requests

Example:

> “Every Monday morning summarize pending requisitions.”

Route:

```text
Intent
↓
Automation Builder
↓
Workflow Proposal
↓
Safety Classification
↓
Schedule Configuration
↓
User Review
↓
Enable
```

The AI may help build the automation definition.

It must not create unsafe workflow logic.

Use existing AI F13 scheduling constraints.

---

# 19. Insight Requests

Examples:

> “What needs my attention?”

> “Show me procurement problems.”

Route to AI F14 Insight Engine.

Results must be filtered by:

- User
- Permission
- Company
- Module

Do not reveal insights outside authorization scope.

---

# 20. Follow-Up Understanding

Support contextual follow-ups.

Example:

```text
User:
Show pending requisitions.

AI:
12 pending requisitions found.

User:
Only the oldest ones.

AI:
Filters previous result set.
```

Maintain structured conversation references.

Do not rely solely on free-text conversation history.

Store lightweight result references where appropriate.

---

# 21. Ambiguity Handling

When a request is ambiguous:

> “Open the order.”

The system should identify possible meanings.

Example:

```text
Do you mean:

1. Purchase Orders
2. Sales Orders
3. A specific order
```

Do not guess.

Avoid unnecessary clarification when confidence is high and scope is safe.

---

# 22. Command Suggestions

Create contextual suggestions.

Examples based on module:

### Procurement

```text
Show pending requisitions
Find a supplier
Review procurement backlog
Check procurement policy
```

### General

```text
What needs my attention?
Show my tasks
Explain this page
Take me somewhere
```

Suggestions must be static/registered or generated from authorized capabilities.

Do not expose unavailable features.

---

# 23. Command History

Store useful command history.

Fields:

- User
- Company
- Conversation
- Input summary
- Intent
- Capabilities used
- Status
- Timestamp

Do not store secrets.

Allow users to view recent commands/conversations according to retention policy.

---

# 24. Command Execution Records

Create a unified execution record.

Logical fields:

- id
- conversation_id
- user_id
- company_id
- command_text_summary
- intent
- capability_chain_json
- status
- started_at
- completed_at
- duration_ms
- error_code

Use references to detailed Agent/Automation/Action records where applicable.

Do not duplicate sensitive outputs unnecessarily.

---

# 25. Request Limits

Implement:

- Maximum decomposed steps
- Maximum tools per command
- Maximum agent delegations
- Maximum context size
- Maximum execution runtime
- Maximum result records
- Maximum concurrent commands per user

When limits are exceeded:

- Stop safely.
- Explain the limitation.
- Suggest narrower request if appropriate.

---

# 26. Provider Compatibility

Command routing may require different AI capabilities.

Examples:

```text
Basic Conversation
→ chat

Intent Classification
→ structured_output preferred

Tool Execution
→ tool_calling

Complex Agent Delegation
→ agent_capable
```

Gracefully degrade.

If structured output is unavailable:

- Use a deterministic fallback only if safe.
- Do not use unsafe parsing for write actions.

---

# 27. Error Handling

Handle:

- No provider configured
- Tool unavailable
- Permission denied
- Ambiguous request
- Context expired
- Record deleted
- Agent unavailable
- Automation disabled
- Provider timeout
- Execution limit

Responses should be understandable.

Example:

> I can’t access purchase orders with your current permissions.

Do not expose stack traces or internal configuration.

---

# 28. Security Requirements

Mandatory:

- Every request capability-routed.
- No direct database access.
- No arbitrary SQL.
- No arbitrary PHP.
- No arbitrary filesystem access.
- No arbitrary URLs.
- User permissions enforced.
- Tenant scope enforced.
- Current page context validated server-side.
- Write actions require confirmation.
- Agent restrictions preserved.
- Automation safety classification preserved.
- Knowledge permissions preserved.
- Navigation routes validated.
- Hidden reasoning never exposed.
- Execution limits enforced.
- Full audit trail.

---

# Important Constraints

Do NOT implement:

- A separate unsafe AI backend bypassing previous modules
- Direct natural language to SQL
- Automatic write execution
- Unrestricted browsing
- Arbitrary API execution
- Arbitrary code generation/execution inside ERP
- Unlimited agent delegation
- Permission elevation

This step is:

**The Unified Natural-Language Interface for Every Existing Safe AI Capability.**

---

# Completion Criteria

AI F15 is complete when:

- Global AI command entry exists.
- Command Center page exists.
- Intent classification works.
- Requests route to existing AI capabilities.
- Multi-intent requests work.
- Current ERP context works.
- Navigation works.
- Natural-language search works.
- Knowledge questions work.
- Agent delegation works.
- Tasks/automations/actions integrate safely.
- Responses clearly distinguish source types.
- Follow-up context works.
- Permissions and tenant isolation are preserved.
- All writes still require confirmation.
- Command execution is audited.

After implementation, provide:

- Files created
- Files modified
- Database changes
- Intent categories
- Capability routing map
- Context architecture
- Command UI locations
- Agent delegation rules
- Follow-up handling
- Security controls
- Known limitations