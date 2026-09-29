Implement **AI F9 — AI Action Framework & Human-Confirmed ERP Actions** in the existing Core PHP + MySQL ERP.

### Objective

Extend the AI Tool Framework to support ERP actions safely.

AI F9 introduces:

- Action registry
- Write/action classification
- Action schemas
- Permission enforcement
- Action preview
- Mandatory user confirmation
- Idempotency protection
- Secure execution
- Action audit trail

The AI may now prepare actions, but must NEVER silently execute a state-changing operation.

Every write/action requires explicit user confirmation through the ERP UI.

---

# 1. Create Central AI Action Framework

Extend the architecture from AI F8.

Conceptual flow:

```text id="a1f9xq"
User Request
     ↓
AI understands intent
     ↓
AI requests Action
     ↓
Action Registry
     ↓
Authorization
     ↓
Input Validation
     ↓
Action Preview
     ↓
USER CONFIRMATION
     ↓
Idempotency Validation
     ↓
ERP Service Execution
     ↓
Result
     ↓
AI Response
```

The AI must not directly:

- Execute SQL
- Call arbitrary PHP methods
- Call arbitrary URLs
- Modify database records directly

Only registered actions may execute.

---

# 2. Action Registry

Create a centralized server-side Action Registry.

Each action must define:

- unique action ID
- display name
- description
- module
- action category
- risk level
- required permissions
- confirmation requirement
- input schema
- preview builder
- execution handler
- enabled status

Conceptual example:

```php id="xq67p3"
[
    'id' => 'procurement.create_supplier',
    'name' => 'Create Supplier',
    'module' => 'procurement',
    'category' => 'create',
    'risk_level' => 'medium',
    'requires_confirmation' => true,
    'required_permissions' => [
        'actual.existing.permission'
    ]
]
```

Use actual ERP permission names.

Do not invent permissions where equivalent permissions already exist.

---

# 3. Action Contract

Create a reusable Core PHP action interface/contract.

Logical lifecycle:

```text id="3wqz8b"
getDefinition()
validateInput()
authorize()
buildPreview()
createConfirmationToken()
execute()
normalizeResult()
```

Every action must independently enforce:

- Authentication
- Permission checks
- Company/tenant scope
- Input validation
- Business rules

Do not rely only on the AI layer.

The existing ERP service/business logic must remain authoritative.

---

# 4. Mandatory Confirmation System

Create a secure confirmation workflow.

When AI prepares an action:

1. Action is validated.
2. Action preview is generated.
3. No database modification occurs.
4. A pending action is created server-side.
5. User sees a clear confirmation UI.
6. User explicitly clicks Confirm.
7. Backend validates confirmation request.
8. Action executes once.

The confirmation UI must show:

- What will happen
- Relevant records
- Important field values
- Any irreversible impact where applicable

Example:

```text id="wifcuh"
Create Supplier

Name: ABC Trading LLC
Email: purchasing@abc.com
Status: Active

[Cancel] [Confirm & Create]
```

Do not use vague confirmation text like:

> Are you sure?

---

# 5. Pending Action Storage

Create a database structure for pending AI actions.

Suggested logical fields:

- id
- user_id
- company_id / tenant scope
- conversation_id
- action_id
- action_input_json
- action_preview_json
- status
- confirmation_token/hash
- expires_at
- executed_at
- execution_result_json
- created_at
- updated_at

Suggested statuses:

```text id="4frqhk"
pending
confirmed
executing
completed
cancelled
expired
failed
```

Requirements:

- Pending actions belong to the initiating user.
- Confirmation tokens must be unpredictable.
- Tokens must expire.
- Tokens must be single-use.
- Action input must not be mutable after preview unless a new pending action is created.
- Sensitive values must not be unnecessarily stored.

---

# 6. Confirmation Token Security

Implement confirmation tokens securely.

Requirements:

- Cryptographically secure random generation.
- Store a hash where practical.
- Never trust action ID/input from frontend during confirmation.
- Confirmation endpoint should resolve the pending action server-side.
- Verify:
  - User identity
  - Tenant/company scope
  - Token validity
  - Pending status
  - Expiration
  - Single-use state

Never allow:

```text id="ssosf4"
POST /execute?action=create_supplier&name=...
```

as a direct execution path.

Execution must originate from a valid pending action.

---

# 7. Idempotency Protection

Prevent duplicate execution.

Handle:

- Double-click confirmation
- Browser retry
- Network retry
- Multiple tabs
- Concurrent requests

Use database transactions/locking where appropriate.

A confirmed action must execute at most once.

Store idempotency/execution state server-side.

---

# 8. Action Provider Integration

Extend AI provider abstraction from AI F8.

AI may request an action through structured tool/function calling.

Action definitions should be available only when:

- Action is enabled
- User has permission
- Model supports required capability
- Module is active

The AI request must clearly instruct:

- Actions are proposals.
- Actions are never executed directly by the AI.
- A human confirmation step is mandatory.

For models without native structured tool calling:

Do not use unsafe regex extraction to execute actions.

Do not allow actions through free-form AI text.

---

# 9. Initial Procurement Actions

Implement only actions corresponding to existing Procurement functionality and existing ERP business logic.

Do not duplicate business logic.

Reuse the same validated services/controllers used by normal ERP UI wherever safely possible.

Start with low/medium-risk actions that genuinely exist.

Potential examples, ONLY if corresponding functionality already exists:

### Create Supplier

```text id="qwbuxx"
procurement.create_supplier
```

AI gathers required information.

Example:

> Create a supplier called ABC Trading.

If required information is missing:

AI asks follow-up questions.

Once complete:

- Validate input.
- Build preview.
- Create pending action.
- Show confirmation.

Only after explicit confirmation:

- Call existing supplier creation business logic.

---

### Create Purchase Requisition

Only if the existing Procurement module supports this cleanly.

```text id="wcrbvg"
procurement.create_purchase_requisition
```

AI may gather:

- requester
- department
- required items
- quantities
- required date
- justification

Do not execute until confirmed.

---

Do NOT implement high-risk actions yet such as:

- Approvals
- Payment release
- Vendor payment
- Bulk deletion
- Financial posting
- Irreversible workflow transitions

---

# 10. AI Information Gathering

Allow the AI to collect missing action parameters conversationally.

Example:

```text id="0d0p1g"
User:
Create a supplier called ABC Trading.

AI:
Sure. What is the supplier's contact email?

User:
purchasing@abc.com

AI:
What country should I assign?

User:
UAE
```

The AI must not create the record until all required data is available and validated.

Once ready:

```text id="mqm7ma"
AI:
I have prepared the supplier creation request.

[Review & Confirm]
```

The actual pending action input must be validated server-side.

Do not rely on conversation text as authoritative input.

---

# 11. Action Preview

Create a structured action preview system.

The preview must be generated server-side.

Do not simply display raw AI-generated JSON.

Preview should show human-readable:

- Action title
- Field labels
- Values
- Important warnings
- Affected module

Allow each action to customize its preview.

---

# 12. Confirmation UI

Create reusable UI components.

States:

### Pending

Show:

- Action details
- Cancel
- Confirm

### Executing

Disable controls.

Show progress state.

### Completed

Show:

- Success message
- Created/affected record reference
- Valid navigation link where applicable

### Failed

Show:

- Safe error
- Retry only if safe and does not risk duplicate execution

Do not automatically retry write actions.

---

# 13. Action Cancellation

Allow the initiating user to cancel a pending action.

Rules:

- Pending action becomes cancelled.
- Cancelled actions cannot execute.
- Cancellation is logged.
- Confirmation token becomes unusable.

---

# 14. Action Expiration

Pending actions must expire.

Use a configurable expiration period.

When expired:

- Status becomes expired.
- Confirmation fails safely.
- User must request a new action.

Do not silently extend expired confirmations.

---

# 15. Action Re-Authorization

Immediately before execution, re-check:

- User authentication
- User permissions
- Company/tenant scope
- Action enabled status
- Business prerequisites

A pending action created earlier must not bypass permissions that have since been revoked.

---

# 16. Business Logic Reuse

This is critical.

AI actions must NOT create duplicate Procurement business logic.

Example:

```text id="0olxre"
AI Action
   ↓
Existing Procurement Service
   ↓
Existing Validation
   ↓
Existing Database Logic
```

Do not:

```text id="yn31di"
AI Action
   ↓
Direct SQL INSERT
```

The normal ERP service layer remains authoritative.

---

# 17. Action Result Handling

After execution:

- Normalize result.
- Store execution status.
- Show success/failure.
- Provide record reference.
- Provide validated navigation action where applicable.

Example:

```text id="pfn5fw"
Supplier created successfully.

Supplier: ABC Trading
Reference: SUP-00124

[Open Supplier]
```

Do not allow AI to falsely claim success.

The success response must originate from actual execution results.

---

# 18. Audit Trail

Log every important action lifecycle event:

- Action proposed
- Pending action created
- Action previewed
- Action confirmed
- Action cancelled
- Action expired
- Execution started
- Execution completed
- Execution failed

Log:

- user
- action ID
- conversation ID
- timestamp
- result status
- affected record reference where safe

Do NOT log:

- secrets
- credentials
- unnecessary sensitive fields

---

# 19. Action Limits

Implement safeguards:

- Maximum pending actions per user
- Action expiration
- Rate limits
- No unlimited action loops
- Maximum confirmation attempts
- Single-use confirmation
- Server-side concurrency protection

Actions must always remain user-driven.

---

# 20. Security Requirements

Mandatory:

- AI cannot directly execute writes.
- Every action requires explicit confirmation.
- Pending action is server-side.
- Confirmation token is secure and single-use.
- Input cannot be modified after preview.
- Permissions checked before preview and execution.
- Tenant/company scope enforced.
- Existing business rules enforced.
- Idempotency prevents duplicate execution.
- SQL uses prepared statements through existing services.
- No arbitrary PHP method execution.
- No arbitrary SQL.
- No action based purely on free-form AI text.

---

# Important Constraints

Do NOT implement:

- Autonomous write execution
- Auto-confirmation
- Approval actions
- Payment actions
- Financial posting
- Bulk destructive actions
- Background autonomous agents
- Unattended execution
- External side effects without confirmation

This step is strictly:

**AI-Proposed ERP Actions + Explicit Human Confirmation**

---

# Completion Criteria

AI F9 is complete when:

- Action registry exists.
- Action contract exists.
- AI can propose supported actions.
- Required information can be collected conversationally.
- Action preview is generated server-side.
- Pending actions are stored securely.
- User must explicitly confirm.
- Actions execute at most once.
- Existing ERP business logic performs the actual operation.
- Permissions are revalidated.
- Actions are auditable.
- AI cannot silently modify ERP records.

After implementation, provide:

- Files created
- Files modified
- Database changes
- Actions implemented
- Confirmation workflow
- Idempotency approach
- Security controls
- Known limitations