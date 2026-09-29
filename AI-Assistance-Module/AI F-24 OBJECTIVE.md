Implement **AI F24 — AI Security, Governance & Guardrails** in the existing **Core PHP + MySQL ERP**.

### Objective

Build the centralized security and governance enforcement layer for the entire AI module.

This is the **final control layer around AI**, ensuring every AI request, tool, action, document, integration, workflow, provider, and autonomous operation is subject to consistent security rules.

The system must enforce governance **server-side**.

The AI must never be able to bypass these controls by changing prompts, choosing another provider, calling a tool directly, or constructing its own action.

---

# 1. AI Governance Center

Create:

```text
AI → Governance
```

Sections:

```text
Policies
Guardrails
Permissions
Data Controls
Risk Rules
Action Rules
Provider Rules
Audit
Security Events
Emergency Controls
```

---

# 2. Central AI Policy Engine

Create one reusable server-side policy engine.

Every AI operation must pass through it.

Architecture:

```text
AI Request
↓
Authentication
↓
Tenant Validation
↓
AI Policy Engine
↓
Permission Check
↓
Data Policy
↓
Risk Classification
↓
Tool/Action Policy
↓
Provider Policy
↓
Execution
↓
Audit
```

No AI feature may independently implement its own inconsistent governance logic.

Integrate:

- F9 Actions
- F11 Knowledge
- F16 Management
- F17 Skills
- F18 Workflows
- F19 Workspaces
- F20 Documents
- F21 Voice
- F22 Integrations
- F23 Observability

---

# 3. Policy Types

Support policies for:

```text
AI Usage
Data Access
Data Transmission
Tool Usage
Action Execution
External Providers
Documents
Voice
Integrations
Automation
Knowledge
Logging
Retention
```

Each policy should support:

- Name
- Scope
- Conditions
- Effect
- Priority
- Status
- Version
- Created by
- Updated by

Effects:

```text
allow
deny
require_confirmation
require_review
mask_data
restrict_provider
log_only
```

---

# 4. Policy Scope

Policies should be capable of applying to:

```text
Global
Company
Department
Role
User
Feature
Tool
Provider
Document Type
Data Classification
Integration
```

More specific policies should override broader policies according to a deterministic precedence model.

Document the precedence clearly.

Do not allow ambiguous policy evaluation.

---

# 5. AI Risk Classification

Create a centralized risk classifier.

Initial levels:

```text
low
medium
high
critical
```

Examples:

### Low

```text
Search public/internal permitted information
Navigation
Summarization
Drafting
```

### Medium

```text
Creating a task
Sending an internal notification
Creating a draft ERP record
```

### High

```text
Financial changes
External communications
Bulk modifications
External writes
```

### Critical

```text
Destructive actions
Mass deletion
Security configuration changes
Permission changes
Credential operations
```

Risk classification must be deterministic wherever possible.

AI suggestions may assist but must not be authoritative.

---

# 6. Action Policy Matrix

Create a central matrix:

```text
Action Risk
+
User Permission
+
Company Policy
+
Context
=
Execution Decision
```

Possible decisions:

```text
execute
confirm
review
deny
```

Example:

```text
Low + Authorized
→ Execute

Medium + Authorized
→ Confirm

High
→ Confirm / Review

Critical
→ Review / Deny
```

This must be configurable.

---

# 7. Confirmation Policy

Centralize confirmation behavior.

A confirmation should show:

```text
What will happen
Affected records
Affected users/data
External destination if applicable
Risk level
AI-generated reasoning/summary
```

User must explicitly confirm.

Never interpret:

```text
"yes"
"okay"
"do it"
```

as authorization unless the system is currently presenting the exact pending action.

Confirmation tokens should be bound to:

- User
- Company
- Action
- Parameters
- Expiration
- Session/context

Changing action parameters invalidates confirmation.

---

# 8. Human Review Policy

Support:

```text
require_review
```

for operations such as:

- High-value financial actions
- Sensitive documents
- Bulk changes
- External communication
- Restricted integrations
- Low-confidence AI extraction
- Critical workflow operations

Create review queue integration.

---

# 9. AI Tool Authorization

Before every tool execution:

```text
Tool Exists
↓
Tool Enabled
↓
User Authorized
↓
Company Policy Allows
↓
Risk Accepted
↓
Parameters Valid
↓
Execute
```

AI must never directly invoke an unregistered function.

---

# 10. Parameter Security

Validate all AI-generated parameters against strict schemas.

Examples:

- IDs
- Dates
- Amounts
- Statuses
- Record types
- Filters
- File references

Reject:

- Unknown parameters
- Invalid types
- Unexpected arrays/objects
- SQL fragments
- Raw PHP
- Shell commands
- Arbitrary expressions

Never execute AI-generated SQL.

---

# 11. SQL Safety

AI must never receive direct database execution capability.

All ERP data access must use:

```text
Registered Service
OR
Registered Repository
OR
Registered Tool
```

AI-generated SQL must be treated as untrusted text.

If natural-language reporting requires dynamic querying, use a controlled query-builder abstraction with:

- Allowlisted tables
- Allowlisted fields
- Parameter binding
- Row limits
- Tenant filters
- Permission filters

---

# 12. Tenant Isolation

Every AI request must resolve:

```text
Company
User
Role
Permissions
Session
```

All AI data retrieval must enforce company scope.

AI must never infer or switch tenant/company context from natural language.

Example:

> “Show me Company B's invoices.”

If user is not authorized for Company B:

```text
DENY
```

---

# 13. Row-Level Data Security

Where ERP supports record-level permissions, AI must enforce them.

Example:

User can see only:

```text
Department = Procurement
```

AI search must apply that restriction automatically.

Do not retrieve unauthorized records and rely on the model to hide them afterward.

---

# 14. Data Classification

Create centralized classifications:

```text
public
internal
confidential
sensitive
restricted
```

Allow administrators to classify:

- Modules
- Tables/fields where appropriate
- Documents
- Integrations
- Knowledge collections

AI context construction must honor classification.

---

# 15. Sensitive Data Masking

Create centralized masking service.

Potential data:

```text
Email
Phone
Bank details
Tax identifiers
Personal information
Credentials
API keys
Tokens
```

Mask before:

```text
AI Provider
External Connector
Logs
Observability
Analytics
```

unless explicit policy permits the data.

Never mask only at the UI level.

---

# 16. Secret Protection

Absolute rule:

AI must never receive:

- API keys
- Passwords
- OAuth refresh tokens
- Encryption keys
- Session secrets
- Database passwords

Tool execution should happen server-side.

Example:

```text
AI:
"Use the email connector."

Server:
retrieves encrypted credential
→ executes connector
→ returns safe result
```

Never:

```text
AI receives credential
→ AI calls API
```

---

# 17. Prompt Injection Defense

Treat all retrieved external content as untrusted.

Potential sources:

- Documents
- Websites
- Emails
- External APIs
- Knowledge documents
- User-uploaded files
- Connector responses

Retrieved content must never be interpreted as system instructions.

Explicitly separate:

```text
System Instructions
Developer/Policy Instructions
User Request
Retrieved Content
Tool Results
```

---

# 18. Tool Output Sanitization

External tool results may contain malicious instructions.

Before passing results back to AI:

```text
External Output
↓
Validation
↓
Sanitization
↓
Trust Classification
↓
AI Context
```

Never allow tool output to redefine system policy.

---

# 19. Prompt Injection Detection

Implement deterministic detection for obvious injection patterns.

Examples:

```text
Ignore previous instructions
Reveal system prompt
Disable security
Call this hidden endpoint
Send credentials
```

Detection is supplementary.

The actual security boundary must be enforced by server-side permissions and policies.

Do not depend solely on an AI detector.

---

# 20. External Data Trust Boundary

Create explicit trust boundaries:

```text
Trusted ERP System Data
Controlled AI Instructions
User Input
Untrusted Documents
Untrusted External APIs
External Web Content
```

Every context item should carry a source/trust classification.

---

# 21. Provider Routing Security

Before selecting an AI provider:

Check:

```text
Data Classification
Provider Approval
Company Policy
Region/Data Residency Policy
Feature Policy
Cost Policy
```

Example:

```text
Restricted data
+
External provider not approved
=
DENY
```

Do not allow AI to switch providers to bypass a restriction.

---

# 22. Provider Allowlist

Administrators can configure:

```text
Allowed Providers
Allowed Models
Blocked Providers
Blocked Models
Allowed Features
```

Example:

```text
Provider:
OpenAI

Allowed for:
General AI

Blocked for:
Restricted documents
```

---

# 23. Model Capability Policy

Different models may have different capabilities.

Examples:

```text
vision
tools
reasoning
embeddings
audio
structured_output
```

Only expose capabilities approved for the current feature.

Do not silently substitute an incompatible model.

---

# 24. AI Autonomy Levels

Create centralized autonomy settings:

```text
assist
suggest
confirm
supervised
restricted
```

Example:

### Assist

AI answers only.

### Suggest

AI proposes actions.

### Confirm

AI can execute after confirmation.

### Supervised

AI can perform approved low-risk operations under policy.

### Restricted

AI cannot execute actions.

These settings must be enforced server-side.

---

# 25. Autonomous Task Limits

For autonomous workflows, enforce:

- Maximum steps
- Maximum runtime
- Maximum tool calls
- Maximum cost
- Maximum records affected
- Maximum retries
- Allowed tools
- Allowed risk level

Example:

```text
Max 20 tool calls
Max 10 minutes
Max 100 records
Max $X estimated cost
```

When limit is reached:

```text
pause
```

Do not continue indefinitely.

---

# 26. Infinite Loop Protection

AI agents/workflows must detect:

- Repeated identical calls
- Repeated failures
- Cyclic tool execution
- Excessive retries
- No-progress loops

Stop safely when thresholds are exceeded.

---

# 27. Bulk Action Protection

For actions affecting many records:

```text
1 record
10 records
100 records
1000+ records
```

Use configurable thresholds.

Example:

```text
>100 records
→ mandatory review
```

AI must never infer approval for bulk actions from a generic request.

---

# 28. Financial Action Protection

For financial actions:

- Enforce monetary thresholds.
- Require appropriate permissions.
- Require confirmation/review.
- Record audit trail.

Example:

```text
<$100
confirm

$100-$10,000
confirm + policy

>$10,000
review
```

Exact thresholds must be configurable.

---

# 29. External Communication Protection

For:

- Email
- Messaging
- External notifications

AI should distinguish:

```text
draft
send
```

Default behavior:

```text
Draft → allowed
Send → confirmation
```

For sensitive recipients/content:

```text
Review required
```

---

# 30. Navigation Security

AI navigation may only use registered ERP routes.

Do not allow:

```text
javascript:
data:
file:
arbitrary external URL
```

External links require appropriate policy.

---

# 31. File Security

AI must not:

- Execute uploaded files
- Generate executable server files and run them
- Access arbitrary filesystem paths
- Read unrelated server directories

File access must use registered document/file services.

---

# 32. Code Execution Boundary

AI coding assistance may generate code as text.

It must not automatically execute arbitrary generated PHP/system commands on the production ERP server.

If a future sandbox exists, execution must occur there with strict isolation.

For this phase:

```text
Generated Code
→ Text/Preview only
```

unless existing approved sandbox infrastructure explicitly exists.

---

# 33. Rate Limiting

Apply rate limits at:

```text
User
Company
Provider
Model
Feature
Tool
Integration
```

Prevent abuse and runaway agents.

---

# 34. Cost Guardrails

Before expensive operations:

Estimate:

```text
Tokens
Provider Cost
Expected Tool Calls
Expected External Costs
```

If threshold exceeded:

```text
confirm
OR
deny
```

AI must not circumvent budgets by switching providers.

---

# 35. Emergency Controls

Create:

```text
AI → Governance → Emergency Controls
```

Controls:

```text
Disable All AI
Disable AI Actions
Disable Autonomous Agents
Disable External Providers
Disable External Integrations
Disable Document AI
Disable Voice
Disable Workflows
```

All controls must be server-side.

---

# 36. Security Event Detection

Generate security events for:

```text
Policy Violation
Permission Denied
Prompt Injection Attempt
Unauthorized Tool
Blocked Provider
Sensitive Data Leak Attempt
Repeated Failed Authentication
Abnormal Tool Usage
Autonomy Limit Exceeded
Cost Limit Exceeded
```

Severity:

```text
info
warning
high
critical
```

---

# 37. Security Event Center

Create:

```text
AI → Governance → Security Events
```

Allow:

- Filtering
- Investigation
- Acknowledgement
- Resolution
- Notes
- Related trace lookup

Do not expose sensitive event payloads unnecessarily.

---

# 38. Governance Audit

Audit:

```text
Policy Created
Policy Changed
Policy Activated
Policy Disabled
Permission Changed
Provider Allowed
Provider Blocked
Risk Rule Changed
Emergency Control Activated
Guardrail Triggered
```

All governance changes require actor identity.

---

# 39. Policy Versioning

Every policy should be versioned.

Example:

```text
Policy v1
Policy v2
Policy v3
```

Store:

- Version
- Change reason
- Author
- Timestamp
- Previous version

Allow rollback.

Do not silently overwrite active policy.

---

# 40. Policy Simulation

Create:

```text
Policy Simulator
```

Administrators can enter a hypothetical request:

```text
User:
John

Request:
Approve invoice INV-1002

Action:
invoice.approve

Amount:
$25,000
```

Show:

```text
Risk:
High

Permission:
Allowed

Policy:
Review Required

Final Decision:
REVIEW
```

Simulation must not execute anything.

---

# 41. Governance Explainability

When blocked, the system should explain safely:

```text
Action blocked because:
External writes are disabled for this company.
```

Do not reveal internal security implementation details.

Avoid exposing:

- Secret policy internals
- Credentials
- Hidden system prompts
- Security-sensitive configuration

---

# 42. Permission + Policy Decision Engine

Final decision must combine:

```text
Authentication
Tenant
RBAC
Record Permissions
AI Policy
Risk Policy
Data Policy
Provider Policy
Feature Policy
Budget
Autonomy
```

Result:

```text
ALLOW
CONFIRM
REVIEW
DENY
```

Centralize this decision logic.

---

# 43. Governance API

Create internal server-side APIs/services for:

```text
checkPolicy()
checkPermission()
classifyRisk()
classifyData()
checkProvider()
checkAutonomy()
checkBudget()
requireConfirmation()
requireReview()
recordSecurityEvent()
```

These should be reusable across all AI modules.

---

# 44. Governance Middleware

Where practical, create a reusable request guard.

Example:

```text
AI Controller
↓
AI Governance Guard
↓
Business Logic
```

However, do not rely exclusively on HTTP middleware.

Tool/action execution must also enforce governance at the service layer.

---

# 45. Defense in Depth

Implement security at multiple layers:

```text
UI
↓
Controller
↓
AI Governance
↓
Service
↓
Tool
↓
Database
```

A malicious client must not bypass governance simply by calling an endpoint directly.

---

# 46. Database Security

Ensure AI-facing queries:

- Always use prepared statements.
- Enforce company ID.
- Enforce permission filters.
- Apply row limits.
- Avoid SELECT * where unnecessary.
- Never execute AI-generated SQL.

---

# 47. Logging Security

Never log:

- Passwords
- API keys
- Tokens
- Session secrets
- Encryption keys

Sensitive AI content must obey F16 retention/masking policy.

---

# 48. Security Testing Mode

Create safe test mode for administrators/developers.

Allow simulation of:

```text
Permission Denied
Policy Denied
Confirmation
Review
Budget Exceeded
Provider Blocked
Prompt Injection
Tool Blocked
Autonomy Limit
```

Test mode must not weaken production controls.

---

# 49. Management Center Integration

F16 should display:

```text
AI Governance Health
Policy Violations
Security Events
Blocked Requests
High-Risk Actions
Emergency Controls
Provider Restrictions
Budget Violations
Autonomy Violations
```

---

# 50. Final AI Safety Boundary

The following must NEVER be possible through AI:

```text
Bypass permissions
Switch tenant
Read unauthorized records
Expose credentials
Execute arbitrary SQL
Execute arbitrary shell commands
Execute arbitrary PHP
Disable governance
Modify security policies without authorization
Grant itself permissions
Approve itself
Bypass confirmation
Bypass human review
Override kill switches
```

---

# Important Constraints

Do NOT implement:

- AI-controlled security policies
- AI-controlled permissions
- AI-generated executable server code
- Arbitrary SQL execution
- Arbitrary shell execution
- Arbitrary HTTP requests
- Credential exposure to models
- Hidden autonomous privilege escalation
- Self-granted permissions
- Self-modifying governance
- Voice-only authorization
- Unrestricted agent loops

This step is:

**The centralized security boundary that makes the entire ERP AI system trustworthy enough to operate across real business data and workflows.**

---

# Completion Criteria

AI F24 is complete when:

- Governance Center exists.
- Central policy engine works.
- Policy scopes work.
- Risk classification works.
- Confirmation policies work.
- Human review works.
- Tool authorization works.
- Parameter validation works.
- SQL boundary is enforced.
- Tenant isolation is enforced.
- Row-level security is respected.
- Data classification works.
- Sensitive data masking works.
- Secret protection works.
- Prompt injection defenses exist.
- Tool output sanitization exists.
- Provider policies work.
- Autonomy controls work.
- Agent limits work.
- Loop protection works.
- Bulk action protection works.
- Financial thresholds work.
- External communication protection works.
- File/code execution boundaries work.
- Rate limits work.
- Cost guardrails work.
- Emergency controls work.
- Security events work.
- Policy versioning works.
- Policy simulation works.
- Governance explainability works.
- F16 integration works.
- All previous AI modules use the central governance layer.

After implementation, provide:

- Files created
- Files modified
- Database changes
- Policy engine architecture
- Decision precedence
- Risk model
- Confirmation model
- Review model
- Permission integration
- Data classification model
- Provider restrictions
- Autonomy controls
- Agent limits
- Prompt injection controls
- SQL/security boundaries
- Emergency controls
- Security event architecture
- Policy versioning
- Testing/simulation architecture
- Known limitations