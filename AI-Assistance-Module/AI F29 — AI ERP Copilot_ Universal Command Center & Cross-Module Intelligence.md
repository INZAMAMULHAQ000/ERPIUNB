# AI F29 — AI ERP Copilot: Universal Command Center & Cross-Module Intelligence

Build **AI F29 — Universal ERP Copilot** for the existing ERP.

## NON-NEGOTIABLE TECH STACK

- **Core PHP + MySQL only**
- **No frameworks**
- Reuse the existing ERP architecture and all previous AI capabilities.
- Do not introduce Laravel, Symfony, CodeIgniter, React, Vue, Node.js, Python services, or another application framework.
- This feature must **orchestrate existing capabilities**, not duplicate them.

Integrate with:

**F9 Actions → F11 Knowledge → F13 Scheduling → F16 AI Management → F17 Skills → F18 Workflows → F22 Integrations → F23 Observability → F24 Governance → F25 Agents → F26 Intelligence → F27 Documents → F28 Voice**

---

# OBJECTIVE

Create the **single unified AI experience across the ERP**.

The user should no longer need to know which AI feature to use.

They should simply ask:

> "What needs my attention today?"

> "Why are our procurement costs increasing?"

> "Find the supplier quotation for laptops, compare it with the previous one, and prepare a recommendation."

> "Take care of the overdue invoices."

> "Open procurement and show me the biggest issues."

The Copilot determines which existing capabilities are required and orchestrates them safely.

The architecture should become:

**User → Copilot → Understand → Retrieve → Reason → Plan → Govern → Execute → Verify → Explain**

---

## 1. UNIVERSAL AI ENTRY POINT

Make the AI accessible consistently from the ERP:

- global AI button
- dashboard
- module pages
- record pages
- command center
- voice interface
- notifications
- intelligence alerts
- documents
- workflows

The user should be able to invoke AI without leaving their current ERP context.

---

## 2. CONTEXT-AWARE AI

When launched from a specific page, automatically provide safe contextual information.

Examples:

From supplier page:

> "Why are we having problems with this supplier?"

From invoice:

> "Check this invoice."

From purchase order:

> "Is this PO reasonable?"

From dashboard:

> "What should I focus on?"

Context must always be filtered through F24 permissions.

Never assume that page visibility means the AI can access every related record.

---

## 3. INTENT CLASSIFICATION

Classify requests into controlled intents such as:

- question
- navigation
- search
- analysis
- explanation
- document question
- recommendation
- intelligence investigation
- action
- multi-step task
- workflow request
- external integration request
- scheduled task

Do not let the LLM directly select arbitrary execution behavior.

Map intents to registered capabilities.

---

## 4. CAPABILITY ROUTING

Build a centralized capability router.

Example:

**"Read this invoice"**

→ F27

**"What needs attention?"**

→ F26

**"Do this for me"**

→ F25

**"Search our policies"**

→ F11

**"Send this to the supplier"**

→ F22 + F24 + F25

**"Remind me next Monday"**

→ F13

The router must use registered capabilities only.

---

## 5. CROSS-MODULE QUESTIONS

Support questions requiring multiple ERP modules.

Examples:

> "Which suppliers caused the largest procurement delays this quarter?"

May require:

**Procurement + Inventory + Suppliers + Orders + AI analysis**

Another:

> "Which overdue customers are also high-value customers?"

May require:

**Customers + Invoices + Payments + Finance**

The AI should combine authorized data from multiple modules and explain its reasoning using factual evidence.

---

## 6. CROSS-MODULE ACTIONS

Support multi-module operations through F25.

Example:

> "Find overdue POs, identify affected suppliers, create follow-up tasks, and prepare emails."

The Copilot should create a controlled F25 task.

It must not directly execute a chain of database operations.

---

## 7. UNIVERSAL SEARCH

Provide one AI search experience across authorized ERP information.

Search:

- customers
- suppliers
- products
- invoices
- purchase orders
- employees
- projects
- documents
- workflows
- knowledge
- reports

Respect permissions, tenant isolation, document classification, and F24 policies.

---

## 8. RECORD ACTION MENU

On supported ERP records, provide:

**Ask AI**

with contextual actions such as:

- Explain
- Summarize
- Find related records
- Compare
- Check for problems
- Recommend next action
- Create task
- Draft communication

Only expose actions permitted for that record and user.

---

## 9. CONVERSATIONAL FOLLOW-UP

Maintain context naturally.

Example:

User:

> "Show me overdue purchase orders."

AI:

> "There are 12."

User:

> "Which supplier is the worst?"

AI should understand the previous context.

User:

> "Prepare a message."

AI should know the intended supplier/context.

However, conversational context must never override server-side authorization.

---

## 10. "DO IT" SEMANTICS

Support natural commands such as:

> "Do it."

> "Handle this."

> "Fix this."

> "Take care of it."

Resolve these only when there is an unambiguous preceding action/context.

If multiple actions are possible:

> "Which one would you like me to handle?"

Do not guess when ambiguity could cause a business action.

---

## 11. PLAN-FIRST EXPERIENCE

For meaningful multi-step tasks, show:

### Proposed Plan

1. Find overdue purchase orders.
2. Group them by supplier.
3. Identify priority suppliers.
4. Prepare draft communications.
5. Show drafts for approval.

Then:

**Execute Plan**

or

**Change Plan**

or

**Cancel**

Use F25 for execution.

---

## 12. EXPLAINABILITY

For important responses provide:

- Answer
- Evidence
- Reasoning summary
- Recommended action

Do not expose hidden chain-of-thought.

Instead provide concise business reasoning:

> "I flagged these suppliers because their average delivery delay increased from 4 to 11 days over the last three months."

---

## 13. PROACTIVE + REACTIVE AI

Combine F26 intelligence with conversational AI.

Example:

The ERP detects:

> "Supplier performance has deteriorated."

User clicks:

**Investigate**

Copilot can automatically:

- retrieve supplier history
- inspect purchase orders
- compare delivery performance
- inspect relevant documents
- summarize findings
- recommend next steps

Any resulting action still goes through F25/F24.

---

## 14. DOCUMENT-AWARE COPILOT

From F27, allow:

> "Explain this contract."

> "Compare these quotations."

> "Check this invoice against the PO."

> "Find similar supplier contracts."

Use document evidence and normal permissions.

---

## 15. VOICE-AWARE COPILOT

F28 voice requests must enter the exact same Copilot pipeline.

There must not be a separate voice command executor.

Architecture:

**Voice → Transcription → Copilot → Governance → Existing Capability**

---

## 16. PERSONAL AI HOME

Create an AI home experience containing:

- Ask anything
- Today's priorities
- Recent conversations
- Active tasks
- Pending approvals
- Important alerts
- Recent documents
- Suggested actions
- Quick commands

Personalize based on user permissions and preferences.

---

## 17. QUICK COMMANDS

Provide optional suggestions such as:

- "What's important today?"
- "Show overdue items."
- "Analyze procurement."
- "Find unusual transactions."
- "Summarize my tasks."
- "Review recent documents."

Suggestions must be generated from capabilities actually available to the user.

---

## 18. ACTION CONFIRMATION UX

Before sensitive actions, show:

**Action**

**Why**

**Affected records**

**Risk**

**External impact**

**Approval required**

**Estimated cost**

Then:

**Confirm**

or

**Cancel**

Use F24's existing confirmation binding.

Do not create a weaker confirmation system.

---

## 19. ERROR RECOVERY

When a capability fails:

Explain:

- what failed
- what succeeded
- what remains
- what the user can do next

Example:

> "I reviewed 8 of 10 purchase orders. Two could not be retrieved because the supplier records are unavailable. No messages were sent."

Never hide partial failure.

---

## 20. TASK HANDOFF

If the request becomes long-running:

Automatically transition to F25.

Example:

> "I'll handle this as a background task. You can continue using the ERP."

Show:

- task status
- progress
- approvals
- completion
- failure

---

## 21. NAVIGATION

The Copilot should be able to provide secure ERP destinations.

Examples:

> "Open the supplier."

> "Take me to overdue invoices."

> "Show procurement dashboard."

Navigation must be generated from registered ERP routes.

Never allow arbitrary URLs.

---

## 22. AI RESPONSE COMPONENTS

Standardize responses into components:

- answer
- table
- chart/reference where existing ERP UI supports it
- record links
- document links
- action buttons
- recommendation
- warning
- approval request
- task progress

Do not rely exclusively on plain text.

---

## 23. RESULT CITATIONS / EVIDENCE

For factual ERP answers, provide clickable references to relevant records where permitted.

Example:

> 7 overdue POs found.

Then:

**View Purchase Orders**

The links must point to normal authorized ERP routes.

---

## 24. COST & PROVIDER ROUTING

Use F16/F23/F24 to determine:

- appropriate provider
- allowed model
- budget
- task complexity
- data sensitivity

Do not automatically send sensitive information to an external provider merely because it is cheaper or faster.

---

## 25. GOVERNANCE

Every Copilot request follows:

**Authentication  
→ Tenant  
→ Intent  
→ Permissions  
→ Data Policy  
→ Capability Authorization  
→ Risk  
→ Provider Policy  
→ Autonomy  
→ Confirmation/Review  
→ Execute  
→ Verify  
→ Audit**

No capability may bypass this pipeline.

---

## 26. OBSERVABILITY

Integrate with F23.

Record:

- conversation
- intent
- capability routing
- tools
- provider/model
- latency
- cost
- governance decisions
- task handoffs
- failures
- final outcome

Never log secrets or unnecessary sensitive content.

---

## 27. FEEDBACK

Allow users to rate responses:

- helpful
- not helpful
- incorrect
- incomplete

For actions:

- successful
- partially successful
- incorrect
- unsafe

Feed feedback into F23 evaluation systems.

Do not automatically modify production behavior from feedback.

---

## 28. PERFORMANCE

The Copilot must avoid unnecessary model calls.

Use deterministic routing for:

- navigation
- simple lookups
- known commands
- straightforward filters

Use AI reasoning only where it provides value.

Cache safe reusable results where appropriate.

---

## 29. DATABASE

Only create tables where existing AI infrastructure does not already provide the required functionality.

Potential tables:

- ai_copilot_sessions
- ai_copilot_messages
- ai_copilot_intents
- ai_copilot_routes

Reuse existing conversation/task/trace tables wherever possible.

Include tenant/company isolation and proper indexes.

---

## 30. HARD BOUNDARIES

The Universal Copilot must NEVER:

- bypass permissions
- access unauthorized modules
- switch tenants
- execute arbitrary SQL
- execute shell/PHP
- call arbitrary URLs
- expose secrets
- directly modify production data outside registered services
- bypass F24
- bypass F25 for autonomous work
- bypass approval/review
- fabricate evidence
- claim failed actions succeeded
- expose hidden prompts/security policies
- create its own capabilities
- grant itself permissions

---

# COMPLETION CRITERIA

F29 is complete when the ERP has **one unified AI brain/interface** through which users can ask questions, search, analyze, navigate, understand documents, investigate proactive alerts, start autonomous tasks, interact by voice, and perform approved actions without needing to know which underlying AI module handles the request.

The user experience should feel like:

> **"There is one AI inside my ERP. I simply tell it what I need."**

while internally remaining:

**Copilot → Registered Capability → F24 Governance → F25/F26/F27/etc. → F23 Observability**