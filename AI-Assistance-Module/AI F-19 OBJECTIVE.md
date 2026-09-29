Implement **AI F19 — AI Collaboration, Team Assistant & Shared Workspaces** in the existing **Core PHP + MySQL ERP**.

### Objective

Transform the ERP AI from a primarily individual assistant into a controlled collaborative assistant for teams.

Users should be able to collaborate with AI around shared business work such as:

- Procurement reviews
- Purchase requisition discussions
- Supplier analysis
- Approval preparation
- Investigation tasks
- Monthly reviews
- Shared AI research
- Team action plans

The AI should understand the difference between:

```text
Private AI Context
        vs
Shared Team Context
```

Nothing private should become shared automatically.

This phase must integrate with existing:

- Conversations
- Tasks
- Knowledge
- Agents
- Insights
- Skills
- Workflows
- Command Center
- Permissions

---

# 1. Shared AI Workspaces

Create:

```text
AI → Workspaces
```

A Workspace is a controlled shared environment where authorized users and AI collaborate around a business objective.

Examples:

```text
Procurement Operations
Monthly Purchasing Review
Supplier Evaluation
Project Alpha
Finance Review
```

Each workspace should have:

- ID
- Name
- Description
- Company ID
- Owner
- Status
- Visibility
- Created at
- Updated at

Visibility:

```text
private
restricted
company
```

Do not allow cross-company workspaces.

---

# 2. Workspace Roles

Support workspace-level roles:

```text
owner
manager
member
viewer
```

Suggested capabilities:

### Owner

- Manage workspace
- Add/remove members
- Configure settings
- Archive workspace

### Manager

- Manage members
- Create shared AI resources
- Run approved collaboration workflows

### Member

- Participate
- Create allowed discussions
- Use shared AI context

### Viewer

- Read-only access

Workspace roles must never override ERP permissions.

A workspace member still requires permission for any underlying ERP capability.

---

# 3. Shared AI Conversations

Allow conversations to be:

```text
private
shared_workspace
```

Example:

> Let's investigate why purchase requisitions are delayed.

Multiple authorized workspace members should be able to:

- Read conversation
- Continue discussion
- Ask AI questions
- Add context
- See AI responses

Each message must clearly identify:

- Author
- Timestamp
- Message type

Message types:

```text
user
ai
system
activity
```

Do not silently share a private conversation.

Sharing requires explicit action.

---

# 4. Convert Private Conversation to Shared

Allow explicit conversion:

```text
Private Conversation
        ↓
Share to Workspace
        ↓
Select Workspace
        ↓
Confirm
        ↓
Shared Conversation
```

Before sharing:

- Show what will become visible.
- Check workspace permissions.
- Require explicit confirmation.

Do not automatically share hidden/private AI memory.

---

# 5. Shared Context

Workspace AI context may include approved references to:

- Conversations
- Tasks
- Insights
- Knowledge collections
- ERP records
- Workflow runs
- Agent results

Use references wherever possible.

Concept:

```text
Workspace Context
├── Shared Conversation
├── Linked Tasks
├── Approved Knowledge
├── Linked ERP Records
├── AI Insights
└── Agent Outputs
```

Do not dump all workspace data into every AI request.

Context selection must remain scoped and relevant.

---

# 6. Context Pinning

Allow members with permission to pin important context.

Examples:

```text
Important Supplier Record
Procurement Policy
Monthly Insight
Agent Research Result
```

Pinned context becomes available as a prioritized candidate for workspace AI requests.

Still apply:

- Permission checks
- Tenant isolation
- Data masking
- Context limits

Pinned does not mean unrestricted.

---

# 7. Workspace AI Instructions

Allow authorized workspace managers to configure shared instructions.

Examples:

> Keep all procurement summaries concise.

> Always cite the relevant ERP records.

> Focus analysis on overdue items.

Store instructions separately from system safety rules.

Hierarchy:

```text
System Safety Rules
        ↓
Company Policies
        ↓
Workspace Instructions
        ↓
User Preferences
        ↓
Current Request
```

Lower instructions cannot override higher safety policies.

---

# 8. Workspace Goals

Allow workspaces to define objectives.

Example:

```text
Goal:
Reduce overdue purchase requisitions.

Success Criteria:
All pending PRs older than 14 days reviewed.
```

AI may use goals as context when helping the workspace.

Goals must not automatically authorize actions.

---

# 9. Shared AI Tasks

Extend F10 Tasks.

Allow workspace-linked tasks:

```text
AI identifies issue
        ↓
Create Shared Task
        ↓
Assign Member
        ↓
Track Progress
```

Task metadata:

- Workspace
- Assignee
- Creator
- Linked AI conversation
- Related ERP record
- Status

Do not expose workspace tasks outside authorized members.

---

# 10. AI Collaboration Suggestions

AI may suggest collaboration actions:

Examples:

> This issue may need procurement manager review.

> Would you like to create a shared task?

> This analysis could be shared with the Procurement workspace.

Suggestions must be:

- Optional
- Permission-aware
- Non-autonomous

Never automatically add users to workspaces.

---

# 11. Shared AI Findings

Create a structured concept:

```text
AI Finding
```

A finding may include:

- Title
- Summary
- Evidence references
- Confidence where applicable
- Source conversation
- Related ERP records
- Status
- Created by AI/system
- Reviewed by human

Statuses:

```text
draft
reviewed
accepted
dismissed
archived
```

AI findings are not automatically facts.

Users must be able to review/dismiss them.

---

# 12. Evidence-Linked Collaboration

Where AI makes a factual statement based on ERP data, support evidence references.

Example:

> Three purchase requisitions have been pending for more than 14 days.

Allow references to authorized:

- ERP records
- Tool results
- Knowledge documents
- Insights

Do not expose records the viewing user cannot access.

Evidence access must be checked again when opened.

---

# 13. Shared Research Sessions

Create optional structured research sessions.

Example:

```text
Research:
Evaluate Supplier Delivery Delays
```

A session may include:

- Objective
- Questions
- AI findings
- Knowledge references
- Agent runs
- Notes
- Final summary

This provides a collaborative workspace for complex investigations.

---

# 14. Multi-User Activity Timeline

Create workspace activity timeline.

Events:

```text
Workspace Created
Member Added
Conversation Shared
Context Pinned
AI Finding Created
Task Created
Agent Run Completed
Workflow Run Linked
Finding Reviewed
```

Show:

- Who
- What
- When

Do not log hidden AI reasoning.

---

# 15. Presence Awareness

If practical with current Core PHP architecture, implement lightweight presence.

Examples:

```text
Viewing
Active recently
Editing
```

Do not require WebSockets.

Use simple heartbeat/AJAX polling only if existing architecture supports it efficiently.

Presence is optional and should not block the core phase.

---

# 16. Collaboration Notifications

Integrate with internal notifications.

Examples:

```text
You were added to Procurement Workspace.
A new AI finding requires review.
You were assigned a workspace task.
A shared research session was updated.
```

Notification recipients must be workspace-authorized.

Do not send private AI content to unauthorized users.

---

# 17. Mention Support

Support controlled mentions where existing user infrastructure allows.

Example:

```text
@Manager please review this finding.
```

Mention resolution must:

- Use company-scoped users
- Respect workspace membership
- Create appropriate notifications

Do not allow mentioning users outside the company/workspace context.

---

# 18. Shared Agent Results

Allow authorized users to share an Agent run result into a workspace.

Flow:

```text
Agent Run
        ↓
Share Result
        ↓
Select Workspace
        ↓
Review Shared Summary
        ↓
Publish
```

Do not automatically share:

- Full hidden execution traces
- Sensitive context
- Raw provider data

Share sanitized result summaries and approved evidence references.

---

# 19. Workspace Knowledge Sources

Allow workspace-specific knowledge configuration.

Example:

```text
Procurement Workspace
├── Procurement Policies
├── Supplier Guidelines
└── Approved Procedures
```

A workspace may reference existing approved collections.

Do not duplicate documents unnecessarily.

Access requires both:

- Workspace authorization
- Knowledge permission

---

# 20. Collaborative Skills

Allow selected published skills to be marked collaboration-compatible.

Example:

```text
Monthly Procurement Review
```

When run in workspace mode:

- Results become shared according to workspace policy.
- Generated tasks can be workspace-linked.
- Findings can be reviewed by members.

Do not make every personal skill automatically collaborative.

---

# 21. Collaborative Workflow Integration

Allow workflow outputs to link to workspaces.

Example:

```text
Delayed Requisition Detected
        ↓
AI Analysis
        ↓
Create Finding
        ↓
Add to Procurement Workspace
        ↓
Notify Members
```

Workflow must explicitly target an authorized workspace.

No automatic broadcast across all workspaces.

---

# 22. Workspace Permissions

Add permissions only if missing:

```text
ai.workspaces.view
ai.workspaces.create
ai.workspaces.manage
ai.workspaces.join
ai.workspaces.share
ai.workspaces.findings.review
```

Runtime access requires:

```text
Company Access
        +
Workspace Membership
        +
Feature Permission
        +
Underlying ERP Permission
```

Workspace membership must never bypass ERP authorization.

---

# 23. Member Management

Workspace owners/managers may:

- Invite users
- Remove users
- Change workspace role

Every membership change must be audited.

When a user is removed:

- Future access immediately stops.
- Previously accessible links must be permission-checked.
- Their private content remains private.

Do not automatically delete their authored content unless policy requires it.

---

# 24. Workspace Archiving

Support:

```text
active
archived
```

Archived workspace:

- Read-only according to policy.
- No new AI actions.
- No new member additions.
- Historical content remains accessible to authorized users.

Archiving must not delete ERP records.

---

# 25. AI Context Isolation

Every AI request must explicitly determine:

```text
Private Context?
Workspace Context?
Both?
```

Default:

```text
Private only
```

Workspace context is included only when:

- User is currently operating in workspace context, or
- User explicitly requests it.

Never silently mix private conversations with workspace context.

---

# 26. Cross-Conversation Context Rules

Do not automatically let AI search all conversations.

Allow controlled context references only.

Example:

```text
Current Conversation
+
Pinned Workspace Context
+
Explicitly Linked Research Session
```

This prevents accidental context leakage.

---

# 27. Workspace Data Retention

Integrate with F16 retention policies.

Configure retention for:

- Shared conversations
- Findings
- Research sessions
- Activity logs
- Shared AI summaries

Retention must be:

- Company-aware
- Auditable
- Policy-driven

Do not delete required ERP audit records.

---

# 28. Shared Conversation Export

Allow authorized export only if existing ERP export permissions support it.

Export should include:

- Visible messages
- Findings
- Approved evidence references

Do not export:

- Hidden reasoning
- API/provider secrets
- Unauthorized ERP records
- Private user context

---

# 29. AI Meeting / Review Mode

Create a structured collaboration mode.

Example:

```text
Procurement Weekly Review
```

AI assists by:

1. Summarizing open items
2. Showing relevant insights
3. Tracking discussed issues
4. Creating proposed tasks
5. Producing review summary

All generated tasks/actions still require normal permissions and confirmations.

This is not real-time voice conferencing yet.

---

# 30. Review Summary Generation

Allow authorized users to request:

> Summarize this workspace discussion and create an action list.

AI output should produce:

```text
Summary
Key Decisions
Open Questions
Proposed Actions
Responsible Persons
Evidence References
```

Do not automatically assign people unless explicitly approved.

---

# 31. Workspace Analytics

Provide administrator/workspace analytics:

- Active workspaces
- Members
- Shared conversations
- Findings created
- Findings reviewed
- Tasks completed
- Research sessions
- AI usage

Use aggregated statistics where possible.

Do not expose private conversation metrics to unrelated users.

---

# 32. Collaboration Safety

AI must not:

- Add members automatically
- Share conversations automatically
- Publish findings as facts automatically
- Assign tasks without permission
- Change workspace access
- Expose private context
- Bypass ERP permissions

Collaboration actions involving sharing must be explicit and auditable.

---

# 33. Audit Logging

Audit:

```text
Workspace Created
Workspace Updated
Member Added
Member Removed
Role Changed
Conversation Shared
Context Pinned
Finding Created
Finding Reviewed
Research Session Started
Agent Result Shared
Workspace Archived
```

Store actor, timestamp, target reference, and safe metadata.

Do not log unnecessary sensitive AI content.

---

# 34. Management Center Integration

F16 Management Center should show:

- Number of active workspaces
- Shared AI usage
- Failed collaboration operations
- Workspace-related AI costs
- Feature health

Administrators should have governance visibility without automatically gaining access to all private workspace content.

---

# 35. Kill Switch Integration

Add server-side controls:

```text
Disable AI Workspaces
Disable Shared Conversations
Disable Collaborative Findings
Disable Shared Agent Results
Disable Workspace AI Context
```

When disabled:

- New operations stop.
- Existing data remains intact.
- ERP core remains unaffected.

---

# 36. Security Requirements

Mandatory:

- Company isolation
- Workspace membership enforcement
- Underlying ERP permission enforcement
- Explicit sharing only
- Private context isolation
- Context size limits
- Sensitive data masking
- Evidence access revalidation
- Audit logging
- Retention policies
- Server-side kill switches
- No hidden AI reasoning storage

---

# Important Constraints

Do NOT implement:

- Public workspaces
- Cross-company sharing
- Automatic user invitations
- Automatic sharing of private conversations
- AI-controlled workspace permissions
- Unrestricted conversation search
- Hidden behavioral surveillance
- Real-time collaborative editing requiring complex infrastructure

This step is:

**A controlled collaboration layer that allows teams to work with AI together without breaking privacy, permissions, or tenant isolation.**

---

# Completion Criteria

AI F19 is complete when:

- Shared workspaces exist.
- Workspace roles work.
- Conversations can be explicitly shared.
- Private context remains private.
- Shared context is controlled.
- Context pinning works.
- Workspace instructions work.
- Shared tasks work.
- AI findings work.
- Evidence references work.
- Research sessions work.
- Agent results can be safely shared.
- Workspace knowledge references work.
- Collaboration notifications work.
- Permissions are enforced.
- Workspace archiving works.
- Retention works.
- Audit logging works.
- F16 governance integration works.

After implementation, provide:

- Files created
- Files modified
- Database changes
- Workspace architecture
- Role model
- Shared conversation model
- Context isolation rules
- Finding architecture
- Evidence reference approach
- Research session structure
- Permission model
- Retention approach
- Security controls
- Known limitations