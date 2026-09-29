# AI F34 — AI Personalization, Memory & Business Execution Layer

Implement **F34 — AI Personalization, Memory & Business Execution Layer** as the **FINAL major AI feature** in the ERP.

After F34, **do not create another numbered AI feature**. The next and only remaining phase is final integration, security hardening, performance testing, and production readiness.

## Core requirement

Build the final layer that makes the ERP AI feel like a **persistent, personalized, context-aware business assistant** rather than a collection of separate AI features.

The assistant should remember appropriate user preferences and working context, understand recurring goals and routines, and help users move from:

**Question → Understanding → Decision → Plan → Execution → Follow-up**

while remaining completely governed by F24.

**Technology constraints are mandatory:**
- Core PHP
- MySQL
- No frameworks
- Follow the existing ERP architecture.
- Reuse F23–F33 wherever possible.
- Do not introduce a framework or duplicate existing AI infrastructure.

---

# 1. AI Personal Assistant Profile

Create:

**AI → My Assistant**

with:

- Assistant Overview
- Preferences
- Memory
- Goals
- Routines
- Tasks
- Follow-Ups
- Briefings
- Suggestions
- Activity
- Privacy Controls

The user should have a persistent AI experience across the ERP.

---

# 2. User Preferences

Allow the assistant to remember explicitly configured preferences such as:

- preferred language
- preferred response length
- preferred communication tone
- preferred currency/display conventions
- preferred dashboard information
- preferred notification style
- frequently used modules
- preferred reports
- preferred working hours
- preferred briefing time
- preferred analytical comparisons

Do not infer sensitive personal characteristics.

Users must be able to inspect and delete remembered preferences.

---

# 3. Explicit AI Memory

Implement controlled AI memory.

Memory categories may include:

```text
User Preference
Working Context
Business Preference
Repeated Instruction
Approved Shortcut
Important Context
```

Example:

> “When I ask for sales reports, compare against the previous month.”

The user can explicitly save this as a preference.

The assistant may then apply it to future requests where appropriate.

---

# 4. Memory Safety

Memory must never become an authorization mechanism.

A remembered instruction such as:

> “Always approve invoices for me.”

must NOT grant approval authority.

Every action still passes:

**Authentication → RBAC → F24 Governance → Action Policy → Confirmation/Approval**

Memory can influence **how the assistant helps**, never **what the user is authorized to do**.

---

# 5. Memory Transparency

Whenever memory materially influences an answer/action, provide a concise indication such as:

> “Using your saved reporting preference…”

Allow:

- View memory
- Edit memory
- Forget memory
- Forget all
- Disable memory

Do not expose hidden system prompts or internal reasoning.

---

# 6. Memory Scope

Support appropriate scopes:

- personal
- team where explicitly permitted
- department where explicitly permitted
- company-wide approved knowledge

Default to personal memory.

Never allow a user's private memory to become visible to another user.

---

# 7. Business Context Memory

Allow authorized users to save useful business context such as:

- preferred KPI definitions
- recurring report requirements
- recurring follow-up patterns
- preferred process filters
- preferred document formats
- approved communication style
- frequently used workflow context

These must reference controlled ERP entities rather than storing arbitrary executable instructions.

---

# 8. Goals

Create AI business goals.

Examples:

> “Reduce invoice processing time.”

> “Improve quotation conversion.”

> “Reduce overdue receivables.”

> “Improve purchasing SLA compliance.”

Each goal may contain:

- title
- description
- owner
- metric
- baseline
- target
- timeframe
- status
- priority
- related modules
- related processes
- related KPIs

Use F30 metrics where possible.

---

# 9. Goal Tracking

Track goal progress using actual ERP data.

Display:

- current value
- baseline
- target
- progress
- trend
- timeframe
- confidence
- blockers
- supporting evidence

Never fabricate progress.

If data is insufficient, state that clearly.

---

# 10. AI Goal Assistant

Allow users to ask:

> “How are we doing on reducing invoice delays?”

> “What is blocking this goal?”

> “What should I focus on this week?”

The assistant should combine:

- F30 analytics
- F31 process intelligence
- F26 proactive intelligence
- F33 knowledge
- F25 tasks

to provide grounded recommendations.

---

# 11. Goal → Action Plans

Allow users to turn a goal into an action plan.

Example:

```text
Goal
 ↓
Current State
 ↓
Problems
 ↓
Recommended Actions
 ↓
Tasks
 ↓
Monitoring
 ↓
Progress
```

AI may propose the plan.

Production actions require normal authorization.

---

# 12. Recurring Routines

Create controlled AI routines.

Examples:

- morning briefing
- weekly sales review
- overdue invoice review
- procurement health review
- weekly process review
- monthly executive briefing
- supplier follow-up review

A routine should define:

- owner
- schedule
- scope
- data sources
- output
- allowed actions
- notification destination
- enabled/disabled

Use the existing scheduler.

---

# 13. Daily AI Briefing

Create a personalized briefing combining relevant information from:

- F26 intelligence
- F30 analytics
- F31 process intelligence
- F33 knowledge
- pending F25 tasks
- important ERP events

Example:

> **Good morning.**
>
> 3 items need attention:
> - 7 invoices are approaching overdue.
> - Finance approval time increased 18%.
> - 2 supplier deliveries are past their expected dates.

Every statement must link to supporting evidence.

---

# 14. Role-Aware Briefings

Briefings should differ according to authorized business role.

Examples:

**CEO/Executive**
- revenue
- cash
- major risks
- business trends
- strategic goals

**Finance**
- receivables
- payables
- approvals
- financial exceptions

**Sales**
- pipeline
- quotations
- customer follow-ups

**Procurement**
- supplier delays
- purchase approvals
- inventory/procurement exceptions

Never expose information outside the user's permissions.

---

# 15. Personalized Suggestions

The assistant may proactively suggest:

- review this alert
- follow up with this customer
- investigate this bottleneck
- review this document
- complete this task
- inspect this KPI
- update an outdated knowledge item

Suggestions are advisory.

Do not silently execute them.

---

# 16. Smart Follow-Ups

Combine F25/F26/F32 to identify unfinished work.

Examples:

- drafted communication awaiting approval
- task approaching deadline
- workflow proposal awaiting review
- intelligence alert unresolved
- knowledge review overdue
- business goal falling behind

Provide:

**What happened → Why it matters → Suggested next step**

---

# 17. Conversational Task Continuity

Allow conversations to continue naturally.

Example:

**User:**
> “Show overdue invoices.”

**AI:**
> “There are 27.”

**User:**
> “Only major customers.”

**AI:**
> “There are 8.”

**User:**
> “Draft reminders.”

**AI:**
> “I prepared 8 drafts.”

**User:**
> “Make them more diplomatic.”

**AI:**
> “Updated.”

**User:**
> “Send them.”

The final send still passes F24 confirmation/approval.

The assistant must preserve relevant conversation context without retaining unnecessary data indefinitely.

---

# 18. Context Compression

Implement controlled conversation/context management.

Do not send unlimited conversation history to providers.

Use:

- recent messages
- structured session state
- approved summaries
- relevant memory
- relevant ERP context

Avoid unnecessary sensitive data transmission.

---

# 19. Long-Term Context vs Session Context

Separate:

**Session Context**
- temporary
- conversation-specific
- automatically expires

**Persistent Memory**
- intentionally retained
- user-visible
- editable/deletable

Do not silently convert every conversation into permanent memory.

---

# 20. Memory Expiration

Support expiration for appropriate memory.

Examples:

- temporary project context
- temporary preferences
- temporary workflow context

Allow configurable retention under F24.

Expired memory must not be used.

---

# 21. User Memory Controls

Create clear controls:

- Memory On/Off
- View Memory
- Delete Memory Item
- Delete All Memory
- Export Memory where supported
- Reset Assistant Context

Respect retention policies.

---

# 22. Business Execution Loop

Create the final unified execution pattern:

```text
Understand
   ↓
Retrieve Context
   ↓
Analyze
   ↓
Recommend
   ↓
Plan
   ↓
Govern
   ↓
Confirm / Approve
   ↓
Execute
   ↓
Verify
   ↓
Monitor
   ↓
Follow Up
```

Route execution through the existing modules rather than creating a second execution engine.

---

# 23. Universal Action Handoff

The assistant should be able to hand work to:

- F25 Agent Runtime
- F26 Intelligence
- F27 Document Intelligence
- F28 Voice
- F29 Copilot
- F30 Analytics
- F31 Process Intelligence
- F32 Communications
- F33 Knowledge

The AI should understand which subsystem owns each capability.

Do not duplicate functionality already implemented.

---

# 24. Multi-Step Business Objectives

Support requests such as:

> “Help me reduce overdue receivables.”

The assistant should be able to:

1. inspect authorized receivables data
2. analyze trends
3. identify problem accounts
4. inspect relevant communication history
5. identify follow-up opportunities
6. create a recommended action plan
7. prepare communication drafts
8. create tasks
9. monitor progress

Every step remains governed.

Do not automatically execute high-risk financial actions.

---

# 25. Decision Support

Combine F26/F30/F31/F33.

For a business question, present:

**Facts**
→ actual ERP data

**Analysis**
→ calculations/patterns

**Knowledge**
→ approved policies/procedures

**Recommendation**
→ AI interpretation

**Action**
→ optional governed execution

This becomes the final unified AI decision experience.

---

# 26. Explainability

Do not expose chain-of-thought.

Instead show:

- relevant facts
- calculations
- sources
- assumptions
- confidence
- limitations
- policy used
- governance result

Users should understand **why an action/recommendation was presented** without receiving hidden internal reasoning.

---

# 27. AI Memory + Knowledge Separation

Clearly separate:

**Memory**
from

**Enterprise Knowledge**

Memory represents user/context preferences.

Knowledge represents authoritative organizational information.

A personal memory must never override an approved company policy.

Example:

User memory:

> “I usually approve purchases quickly.”

Company policy:

> “Purchases above threshold require Finance approval.”

The policy and F24 governance always win.

---

# 28. AI Learning From Feedback

Use F23 feedback infrastructure.

Learn operationally from:

- corrections
- accepted/rejected recommendations
- preferred communication style
- repeated successful workflows
- failed tasks
- knowledge feedback

But do not autonomously modify production models, prompts, permissions, policies, or workflows.

Learning should produce:

- preference updates
- evaluation datasets
- improvement suggestions
- administrator review items

---

# 29. Personalization Boundaries

Do NOT personalize based on sensitive characteristics unless explicitly required and legally/organizationally authorized.

Do not infer or store:

- sensitive personal attributes
- passwords
- authentication secrets
- private credentials
- hidden security information

---

# 30. AI Home

Make the existing AI Command Center feel like a unified assistant.

The home screen should provide:

- Ask anything
- Continue conversation
- Today's briefing
- Important alerts
- Current goals
- Pending approvals
- Suggested actions
- Recent documents
- Recent analyses
- Recent tasks
- Saved commands

All content must be permission-filtered.

---

# 31. Quick Commands

Support configurable shortcuts such as:

- “My briefing”
- “What needs attention?”
- “My pending approvals”
- “My overdue tasks”
- “Sales summary”
- “Purchasing issues”
- “Prepare follow-ups”
- “Explain this”
- “Find the policy”
- “Analyze this”

Quick commands must route through the same Copilot/governance infrastructure.

---

# 32. Natural Follow-Up

Support natural references:

> “That one.”

> “Do the same for suppliers.”

> “Only this month.”

> “Make it shorter.”

> “Show me the evidence.”

> “Create the task.”

Resolve references only when the intended object is unambiguous.

If ambiguous, ask.

Never guess an important business target.

---

# 33. Autonomous Monitoring

Allow approved routines to monitor:

- goals
- KPIs
- processes
- alerts
- communications
- documents
- knowledge freshness

When something changes materially:

**Detect → Explain → Recommend → Notify**

Do not silently execute consequential actions.

---

# 34. Autonomous Execution Boundaries

F25 remains the execution engine.

Any autonomous action must respect:

- F24 autonomy level
- action risk
- budget
- step limits
- record limits
- tool restrictions
- provider restrictions
- approval rules
- emergency controls

Memory or personalization must never increase autonomy.

---

# 35. Cross-Module Context

The final assistant should understand relationships across the ERP.

Example:

> “Why is this customer's account becoming risky?”

It may connect:

- invoices
- payments
- orders
- communications
- support
- delivery
- intelligence
- analytics
- documents
- knowledge

But only where the user is authorized to access those areas.

---

# 36. Unified Evidence

Whenever an answer combines multiple modules, show the evidence trail.

Example:

```text
Finding
 ├─ Invoice records
 ├─ Payment history
 ├─ Communication history
 ├─ Process metrics
 └─ Approved policy
```

Users can drill into each authorized source.

---

# 37. Cost-Aware Personalization

Integrate F16/F23/F24.

Use appropriate models/providers based on:

- task complexity
- sensitivity
- cost limits
- latency requirements
- provider capability

Do not select an external provider that violates data policy simply because it is cheaper.

---

# 38. Failure Recovery

If one subsystem fails:

- explain the failure
- preserve successful work
- do not pretend completion
- offer a safe retry
- allow manual continuation

Example:

> “The draft was created, but the external communication provider is unavailable. Nothing was sent.”

---

# 39. Database

Reuse existing AI infrastructure.

Only create tables where genuinely necessary, for example:

```text id="k3c4dz"
ai_user_preferences
ai_user_memory
ai_memory_events
ai_goals
ai_goal_metrics
ai_goal_actions
ai_routines
ai_briefings
ai_personal_suggestions
ai_execution_plans
ai_execution_events
```

Do not duplicate existing F25/F26/F29/etc. tables.

Every persistent table must support appropriate:

- tenant/company isolation
- user ownership
- timestamps
- retention
- status
- audit metadata
- indexes

---

# 40. Security

F24 remains authoritative.

The implementation must NEVER allow:

- memory to grant permission
- memory to override policy
- cross-user memory leakage
- cross-tenant memory leakage
- unauthorized cross-module retrieval
- autonomous approval
- autonomous financial authorization
- unauthorized communication
- arbitrary SQL/PHP/shell execution
- credential exposure
- governance bypass
- emergency-control bypass
- hidden production changes

---

# 41. Privacy

Provide clear controls for:

- memory retention
- conversation retention
- deletion
- sensitive context handling
- provider transmission
- AI activity visibility

Do not retain more data than required.

Respect F24 retention and privacy policies.

---

# 42. Prompt Injection

Treat:

- ERP records
- communications
- documents
- knowledge
- external data
- AI-generated suggestions

as untrusted content.

Persistent memory must also never contain executable instructions capable of overriding system/governance rules.

---

# 43. Observability

Integrate F23.

Track:

- personalization usage
- memory retrieval
- memory creation/deletion
- goal analysis
- routines
- briefings
- execution plans
- action handoffs
- provider/model
- cost
- latency
- failures
- user feedback

Protect sensitive memory and conversation content.

---

# 44. Final AI Architecture

Verify the complete AI architecture now follows:

```text
                    ┌─────────────────────┐
                    │   AI Command Center │
                    │      / Copilot      │
                    └──────────┬──────────┘
                               ↓
                    ┌─────────────────────┐
                    │ Intent + Context    │
                    │ + Personalization   │
                    └──────────┬──────────┘
                               ↓
                    ┌─────────────────────┐
                    │ Capability Router   │
                    └──────────┬──────────┘
                               ↓
       ┌───────────────────────┼────────────────────────┐
       ↓                       ↓                        ↓
   Knowledge              Analytics                Intelligence
     F33                    F30                       F26
       ↓                       ↓                        ↓
 Documents F27        Process F31              Communications F32
       └───────────────────────┼────────────────────────┘
                               ↓
                       Agent Runtime F25
                               ↓
                      F24 Governance
                               ↓
                    Confirm / Review / Deny
                               ↓
                       Approved ERP Tools
                               ↓
                          Verification
                               ↓
                     Observability F23
                               ↓
                    Follow-up / Monitoring
```

Do not create a second competing architecture.

---

# 45. Testing Requirements

### A. Memory
1. Save an explicit user preference.
2. Start a new conversation.
3. Verify the preference can be used.
4. Edit the preference.
5. Delete it.
6. Verify it is no longer used.
7. Test memory disablement.

### B. Memory Isolation
8. Create memory for User A.
9. Log in as User B.
10. Verify User B cannot see/use User A's private memory.
11. Test cross-tenant isolation.

### C. Security
12. Store an instruction in memory attempting to grant permission.
13. Attempt the prohibited action.
14. Verify F24 blocks it.
15. Test memory attempting to override an approved policy.

### D. Goals
16. Create a measurable goal.
17. Link it to an F30 metric.
18. Verify actual progress.
19. Test insufficient data.
20. Verify unsupported progress is not fabricated.

### E. Goal Execution
21. Create an action plan.
22. Convert an approved step into an F25 task.
23. Verify governance.
24. Verify high-risk actions require approval.

### F. Briefings
25. Generate a daily briefing.
26. Verify information comes from actual ERP modules.
27. Verify evidence links.
28. Test role-specific briefing.
29. Verify unauthorized information is excluded.

### G. Routines
30. Create a recurring routine.
31. Run it manually.
32. Verify scheduler execution.
33. Disable it.
34. Verify it stops.

### H. Conversation Continuity
35. Start a multi-step conversation.
36. Change filters naturally.
37. Modify the requested output.
38. Continue with an action.
39. Verify correct context is maintained.
40. Test ambiguous references.

### I. Cross-Module Intelligence
41. Ask a question requiring multiple modules.
42. Verify the correct modules are consulted.
43. Verify evidence from each source.
44. Test unauthorized source access.

### J. Execution
45. Ask the assistant to solve a multi-step business objective.
46. Verify planning.
47. Verify F24 governance at every consequential action.
48. Verify F25 executes only approved actions.
49. Verify final verification.

### K. Failure Recovery
50. Simulate a provider/module failure.
51. Verify successful work is preserved.
52. Verify failed work is clearly reported.
53. Verify no false completion message.

### L. Prompt Injection
54. Put malicious instructions in:
   - memory
   - ERP records
   - documents
   - communications
   - knowledge
55. Verify none can override system/governance instructions.

### M. Cost & Providers
56. Test different task complexity levels.
57. Verify appropriate provider/model routing.
58. Verify F24 provider restrictions.
59. Verify F23 cost tracking.

### N. Privacy
60. Test memory deletion.
61. Test conversation retention.
62. Test sensitive information.
63. Verify provider transmission rules.

### O. Kill Switch
64. Disable AI.
65. Verify personalized AI functions stop.
66. Disable autonomous agents.
67. Verify routines cannot execute actions.
68. Disable external communication.
69. Verify sending remains blocked.

### P. Full Regression

Run the complete ERP AI regression:

**F9 → F11 → F13 → F15 → F16 → F17 → F18 → F19 → F20 → F21 → F22 → F23 → F24 → F25 → F26 → F27 → F28 → F29 → F30 → F31 → F32 → F33 → F34**

Test:

- authentication
- RBAC
- tenant isolation
- AI providers
- integrations
- observability
- governance
- agents
- intelligence
- documents
- voice
- Copilot
- analytics
- process intelligence
- communications
- knowledge
- personalization
- memory
- goals
- routines
- execution

---

# 46. FINAL COMPLETION CRITERIA

F34 is complete only when:

- AI has persistent but controlled personalization
- memory is transparent and user-controlled
- memory cannot grant permissions
- goals and routines work
- personalized briefings work
- cross-module context works
- multi-step execution works
- F25 remains the execution engine
- F24 remains the final authority
- F23 observes the complete AI lifecycle
- F26/F27/F28/F29/F30/F31/F32/F33 integrations work
- tenant/RBAC isolation is verified
- prompt injection is contained
- privacy/retention controls work
- emergency controls work
- performance is acceptable
- full regression passes

## IMPORTANT — ROADMAP FINALITY

**F34 is the final major AI feature.**

Do NOT create F35/F36/F37/etc.

After F34, the project enters:

**FINAL AI INTEGRATION + HARDENING + SECURITY + PERFORMANCE + PRODUCTION READINESS**

That is a completion phase, not another feature roadmap.

The objective is now to make the entire AI system stable, secure, coherent, and production-ready — not to keep adding numbered features.