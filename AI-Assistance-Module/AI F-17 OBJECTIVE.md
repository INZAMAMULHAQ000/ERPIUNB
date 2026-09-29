Implement **AI F17 — AI Skills, ERP Learning & Adaptive Assistance** in the existing **Core PHP + MySQL ERP**.

### Objective

Make the AI progressively more useful inside the ERP by introducing a controlled **Skills System**.

The AI should be able to learn how to perform approved ERP workflows and provide increasingly intelligent guidance without becoming an uncontrolled self-modifying system.

Examples:

- “How do I create a purchase requisition?”
- “Guide me through supplier creation.”
- “What should I do next?”
- “Help me complete this procurement workflow.”
- “Teach me how this module works.”
- “Can you remember my preferred way of reviewing requisitions?”

The result should feel like an AI assistant that understands:

- ERP modules
- Business workflows
- User preferences
- Approved procedures
- Common tasks

But all learning must remain controlled, reviewable, permission-aware, and tenant-safe.

---

# 1. AI Skills Architecture

Create a centralized Skill Registry.

Conceptual structure:

```text
User Request
    ↓
Intent / Context Detection
    ↓
Skill Matching
    ↓
Skill Execution
    ↓
Guidance / Tools / Navigation
    ↓
User Progress
    ↓
Completion / Feedback
```

A Skill is a reusable, controlled capability describing how the AI should assist with a specific ERP activity.

Examples:

```text
Create Purchase Requisition
Review Pending Requisitions
Create Supplier
Approve Purchase Request
Monthly Procurement Review
ERP Navigation Assistant
```

Skills must not contain unrestricted executable PHP code.

---

# 2. Skill Types

Support:

```text
guidance
navigation
workflow
analysis
knowledge
composite
```

### Guidance Skill

Explains a process step-by-step.

Example:

> Help me create a purchase requisition.

---

### Navigation Skill

Guides the user to the correct ERP location.

Example:

> Where do I manage suppliers?

---

### Workflow Skill

Guides a multi-step ERP process.

Example:

```text
Create PR
↓
Add items
↓
Attach documents
↓
Submit
↓
Wait for approval
```

The AI may guide or invoke existing safe capabilities.

---

### Analysis Skill

Uses approved read-only tools.

Example:

> Review pending requisitions.

---

### Knowledge Skill

Uses company documentation and Knowledge/RAG.

---

### Composite Skill

Combines multiple approved skills.

Example:

```text
Monthly Procurement Review

→ Retrieve pending PRs
→ Retrieve delayed POs
→ Check policies
→ Generate summary
→ Suggest next steps
```

---

# 3. Skill Registry

Create a centralized registry/database architecture.

Each skill should include:

- ID
- Name
- Description
- Module
- Category
- Skill type
- Status
- Version
- Required permissions
- Required capabilities
- Input schema
- Step definition
- Output schema
- Safety classification
- Created by
- Approved by
- Created timestamp
- Updated timestamp

Example conceptual structure:

```php
[
    'id' => 'procurement.create_purchase_requisition',

    'name' => 'Create Purchase Requisition',

    'type' => 'workflow',

    'module' => 'procurement',

    'required_permissions' => [
        'actual.existing.permission'
    ],

    'capabilities' => [
        'navigation',
        'guidance',
        'action_request'
    ]
]
```

Use actual ERP permission names.

---

# 4. Skill Definition Format

Use structured skill definitions.

Example:

```json
{
  "id": "procurement.review_pending_requisitions",
  "version": 1,
  "steps": [
    {
      "type": "tool",
      "tool": "registered.procurement.tool"
    },
    {
      "type": "analysis"
    },
    {
      "type": "response"
    }
  ]
}
```

Skill steps must reference only:

- Registered tools
- Registered actions
- Registered navigation targets
- Registered knowledge collections
- Registered AI capabilities

Do not allow arbitrary:

- SQL
- PHP execution
- Shell commands
- URLs
- API calls

---

# 5. Skill Matching

Extend AI F15 Command Router.

When a user submits a request:

```text
User Request
    ↓
Intent Detection
    ↓
Skill Candidate Search
    ↓
Permission Filtering
    ↓
Capability Availability
    ↓
Best Skill Selection
```

Example:

> “Help me review pending procurement work.”

Potential skill:

```text
procurement.review_pending_requisitions
```

The model may suggest candidates, but final selection must be validated server-side.

---

# 6. Skill Suggestions

Show relevant skills based on:

- Current ERP module
- Current page
- User permissions
- Recent activity
- Active AI insights
- Common workflows

Example on Procurement Dashboard:

```text
Suggested AI Skills

• Review Pending Requisitions
• Find Delayed Purchase Orders
• Create Purchase Requisition
• Monthly Procurement Review
```

Do not suggest inaccessible workflows.

---

# 7. Interactive Workflow Guidance

Create guided skill execution.

Example:

```text
AI:
Let's create a purchase requisition.

Step 1 of 5
Choose requesting department.

[Open Requisition Form]
```

The system should track:

- Current step
- Completed steps
- Skipped steps
- User cancellation
- Errors

The AI must not falsely mark an ERP step complete.

Completion should be verified where possible using actual ERP state.

---

# 8. Skill Run Lifecycle

Create:

```text
started
in_progress
waiting_for_user
waiting_for_confirmation
completed
failed
cancelled
expired
```

Store a Skill Run separately from conversation history.

Logical fields:

- ID
- Skill ID
- Skill version
- User ID
- Company ID
- Conversation ID
- Current step
- State
- Context references
- Started at
- Completed at

Do not store excessive sensitive ERP data in the run state.

---

# 9. Workflow State Verification

Where a skill says:

> “Create the requisition”

The system should verify completion through authorized ERP data.

Example:

```text
Step:
Create Purchase Requisition

Expected State:
New requisition record exists
Created by current user
Created after skill started
```

Do not trust AI-generated statements such as:

> “The requisition has been created.”

unless the ERP action/result confirms it.

---

# 10. Human-Guided vs Assisted Skills

Support modes:

```text
guide
assist
```

### Guide Mode

AI explains and navigates.

User performs all ERP actions manually.

### Assist Mode

AI may:

- Retrieve information
- Pre-fill structured suggestions
- Prepare Pending Actions
- Navigate
- Analyze

All writes still follow AI F9 confirmation requirements.

Never silently execute write operations.

---

# 11. Skill Templates

Create reusable skill templates.

Examples:

```text
Step-by-Step Guide
Search + Analyze
Review Checklist
Create Record
Monthly Review
Investigate Issue
```

Administrators can use templates to create controlled skills.

Do not build a free-form arbitrary execution engine.

---

# 12. Administrator Skill Builder

Create:

```text
AI → Management → Skills
```

Authorized administrators can:

- View Skills
- Create Skill
- Edit Draft
- Clone Skill
- Version Skill
- Disable Skill
- Test Skill
- Publish Skill
- Archive Skill

The builder should use structured forms.

Do not require administrators to write PHP code.

---

# 13. Skill Draft and Publishing Workflow

Implement lifecycle:

```text
draft
        ↓
testing
        ↓
approved
        ↓
published
        ↓
deprecated
        ↓
archived
```

Only published skills should be available to normal users.

Every published skill must have:

- Version
- Approval record
- Change summary

---

# 14. Skill Versioning

Do not overwrite a published skill destructively.

Example:

```text
Create Supplier
v1 → published
v2 → draft
v2 → tested
v2 → approved
v2 → published
```

Existing Skill Runs should retain the version they started with.

---

# 15. Skill Testing Sandbox

Provide a controlled test mode.

Administrators should be able to:

- Test skill matching
- Test input validation
- Test navigation
- Test read-only tools
- Preview workflow steps
- Simulate action proposals

Test mode must not accidentally create production ERP records.

For actions:

```text
Dry Run
→ Preview only
```

Do not bypass normal permission validation during testing.

---

# 16. AI-Generated Skill Drafts

Allow authorized administrators to ask AI:

> “Create a skill for reviewing overdue purchase requisitions.”

The AI may generate a **draft skill proposal**.

Flow:

```text
Administrator Request
        ↓
AI Draft Proposal
        ↓
Structured Skill Validation
        ↓
Administrator Review
        ↓
Testing
        ↓
Approval
        ↓
Publish
```

AI-generated skills must never become active automatically.

The generated draft must reference only registered capabilities.

---

# 17. Skill Validation

Before publishing validate:

- Valid skill schema
- Registered capability references
- Permission requirements
- Navigation targets
- Tool availability
- Action safety
- Step limits
- No circular skill references
- No arbitrary execution fields

Reject invalid skills.

---

# 18. Composite Skills

Allow a skill to call other skills only through controlled references.

Example:

```text
Monthly Procurement Review
    ↓
Review Pending Requisitions
    ↓
Review Delayed Orders
    ↓
Check Procurement Insights
```

Implement:

- Maximum nesting depth
- Circular dependency detection
- Total step limits

Do not allow infinite recursion.

---

# 19. Skill Context

Skills may receive controlled context:

```text
Current Module
Current Page
Current Record
User
Company
Conversation References
Authorized Filters
```

Validate context server-side.

Do not allow client-provided context to override tenant scope.

---

# 20. Adaptive Assistance

Implement a controlled preference layer.

The AI may remember user preferences such as:

- Preferred response length
- Preferred explanation style
- Frequently used modules
- Preferred skill mode
- Favorite workflows

Example:

> “When reviewing procurement, always give me a short summary first.”

Store preferences explicitly.

Do not infer or store sensitive personal characteristics.

---

# 21. User Preference Controls

Create:

```text
AI → Preferences
```

Allow users to:

- View remembered preferences
- Edit preferences
- Delete individual preferences
- Disable adaptive assistance
- Clear all AI preferences

The user should remain in control.

---

# 22. Learning From Repeated Workflows

Track anonymized/aggregated workflow patterns where policy allows.

Example:

```text
Many users repeatedly:

Open Procurement
↓
Open Pending PRs
↓
Filter > 7 Days
↓
Review
```

Administrators may see a potential workflow suggestion:

> Suggested Skill: Review Overdue Requisitions

Do not automatically create or publish skills from behavioral patterns.

Do not expose one user's activity history to another user.

---

# 23. Personal Skill Suggestions

The system may recommend skills based on the user's own authorized activity.

Example:

> You frequently review pending requisitions. Would you like to use the “Review Pending Requisitions” skill?

Recommendations should be:

- Optional
- Explainable
- Disableable

Do not create hidden behavioral profiles.

---

# 24. Skill Feedback

After completion allow:

```text
Helpful
Not Helpful
Too Long
Incorrect
```

Optionally:

```text
What should improve?
```

Store feedback separately.

Do not automatically modify published skills based on feedback.

---

# 25. Skill Analytics

Create administrator analytics.

Metrics:

- Skill starts
- Completion rate
- Cancellation rate
- Failure rate
- Average duration
- Most used skills
- User feedback

Use aggregated views.

Do not expose sensitive user workflow details unnecessarily.

---

# 26. Skill Permissions

Add permissions only if missing:

```text
ai.skills.use
ai.skills.view
ai.skills.manage
ai.skills.publish
ai.skills.test
```

Integrate with existing role architecture.

A user must satisfy both:

1. Permission to use the skill.
2. Permission for every underlying capability.

A Skill must never elevate permissions.

---

# 27. Skill Availability

Before execution check:

```text
Skill Published?
Skill Enabled?
User Authorized?
Company Authorized?
Dependencies Available?
Provider Available?
Required Tools Available?
Required Actions Enabled?
```

If unavailable, show a clear reason.

Do not partially execute an invalid skill.

---

# 28. Skill Audit Logging

Audit:

- Skill created
- Skill edited
- Skill tested
- Skill approved
- Skill published
- Skill disabled
- Skill started
- Step completed
- Action requested
- Skill completed
- Skill failed

Do not log hidden reasoning or unnecessary sensitive context.

---

# 29. Safety Limits

Implement:

```text
Maximum Steps Per Skill
Maximum Skill Runtime
Maximum Tool Calls
Maximum Nested Skills
Maximum Context Size
Maximum Concurrent Skill Runs
```

Integrate with AI F16 policy hierarchy.

Hard limits remain server-controlled.

---

# 30. Kill Switch Integration

Integrate with F16.

Administrator should be able to disable:

```text
AI Skills
Skill Execution
AI Skill Draft Generation
Adaptive Suggestions
```

Disabled features must be enforced server-side.

---

# 31. Security Requirements

Mandatory:

- Structured skill definitions only.
- No arbitrary SQL.
- No arbitrary PHP.
- No shell execution.
- No unrestricted APIs.
- Registered capability references only.
- Permission checks at skill and step level.
- Tenant isolation.
- Published version immutability.
- AI-generated drafts require review.
- Writes require F9 confirmation.
- Workflow completion verified from ERP state.
- Execution limits enforced.
- Skill runs audited.
- User preferences controllable.
- No hidden autonomous self-modification.

---

# Important Constraints

Do NOT implement:

- Self-modifying AI
- Automatic skill publishing
- AI-generated PHP execution
- Automatic permission escalation
- Hidden user profiling
- Autonomous learning that changes business logic
- Unbounded workflow recursion
- Silent write actions

This step is:

**A Controlled ERP Skill System that makes the AI progressively more helpful while remaining governed and safe.**

---

# Completion Criteria

AI F17 is complete when:

- Skill Registry exists.
- Skill types work.
- Skills can guide ERP workflows.
- Skill Runs track progress.
- Workflow completion is verified.
- Administrator Skill Builder exists.
- Draft/testing/approval/publishing workflow works.
- Versioning works.
- AI can propose draft skills safely.
- Composite skills have recursion protection.
- Adaptive user preferences work.
- Users can control/delete preferences.
- Skill suggestions work.
- Feedback and analytics work.
- Permissions are enforced.
- F16 policies and kill switches apply.

After implementation, provide:

- Files created
- Files modified
- Database changes
- Skill types
- Skill lifecycle
- Versioning strategy
- Builder architecture
- Testing/sandbox approach
- Adaptive preference model
- Skill matching strategy
- Safety limits
- Permission model
- Audit events
- Known limitations