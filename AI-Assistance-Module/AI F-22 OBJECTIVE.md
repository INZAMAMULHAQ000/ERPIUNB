Implement **AI F22 — AI Integration & External Connectors** in the existing **Core PHP + MySQL ERP**.

### Objective

Build a controlled Integration & Connector framework that allows the AI module and ERP to connect with approved external systems.

The goal is to eventually support integrations such as:

- Email providers
- Cloud storage
- Calendar systems
- CRM platforms
- Accounting systems
- Messaging platforms
- External business APIs
- Webhooks
- Internal company services

Examples:

> “Check my connected calendar for tomorrow's procurement review.”

> “Create a draft email using the supplier information.”

> “Import approved supplier data from an external system.”

> “Notify the team through the connected messaging service.”

However, this must **not become unrestricted API execution**.

All integrations must be registered, permission-controlled, auditable, policy-governed, and safely callable by AI.

---

# 1. Integration Center

Create:

```text
AI → Integrations
```

Main sections:

```text
Connectors
Connections
Credentials
Capabilities
Webhooks
Sync Jobs
Activity
Health
```

This becomes the central management area for external integrations.

---

# 2. Connector Architecture

Create a reusable connector abstraction.

Concept:

```text
External Service
      ↓
Connector Definition
      ↓
Connection
      ↓
Credential
      ↓
Capability Registry
      ↓
AI / Workflow / ERP
```

Separate these concepts clearly.

### Connector

Defines how a service works.

Example:

```text
Google Workspace Connector
Microsoft 365 Connector
Slack Connector
Generic REST Connector
```

### Connection

A company-specific configured connection.

Example:

```text
Company A Google Workspace
Company B Slack Workspace
```

### Credential

Secure authentication material.

### Capability

A specific allowed operation.

Example:

```text
calendar.read_events
calendar.create_event
mail.create_draft
storage.search_files
```

---

# 3. Connector Registry

Create a centralized connector registry.

Each connector definition should include:

- Connector ID
- Name
- Description
- Category
- Version
- Authentication types
- Supported capabilities
- Configuration schema
- Status
- Safety classification
- Created/updated metadata

Statuses:

```text
available
disabled
deprecated
maintenance
```

Do not allow arbitrary runtime connector code.

Connectors must be registered server-side.

---

# 4. Initial Connector Categories

Support architecture for:

```text
email
calendar
storage
messaging
crm
accounting
webhook
rest_api
internal_service
```

Do not attempt to fully implement every external service in this phase.

Build the framework plus a small number of representative connectors.

---

# 5. Connection Management

Create:

```text
AI → Integrations → Connections
```

Each connection should include:

- Company ID
- Connector
- Display name
- Status
- Authentication state
- Configuration
- Enabled capabilities
- Created by
- Created at
- Last health check

Statuses:

```text
draft
connecting
active
expired
failed
disabled
revoked
```

Connections must always be company-scoped unless explicitly designed otherwise.

---

# 6. Credential Management

Reuse or extend existing secure credential infrastructure.

Credentials may include:

- API keys
- OAuth access tokens
- Refresh tokens
- Client credentials
- Webhook secrets

Requirements:

- Encrypt at rest
- Never expose plaintext after saving
- Mask UI display
- Server-side access only
- Audit credential changes
- Support credential rotation
- Support revocation

Do not store credentials inside normal configuration JSON without encryption.

---

# 7. Authentication Provider Abstraction

Support authentication strategies:

```text
api_key
oauth2
bearer_token
basic_auth
custom_header
webhook_secret
```

Create reusable interfaces.

Do not duplicate authentication logic inside every connector.

OAuth state must include CSRF protection and expiration handling.

---

# 8. Capability Registry

This is critical.

Every external operation must be explicitly registered.

Example:

```text
Connector: Calendar

Capabilities:

calendar.read_events
calendar.search_events
calendar.create_event
```

Each capability defines:

- Unique capability ID
- Connector
- Description
- Input schema
- Output schema
- Permission requirement
- Safety classification
- Read/write classification
- Confirmation requirement
- Rate limit policy

AI must never construct arbitrary API requests directly.

---

# 9. Connector Capability Execution

Execution flow:

```text
AI Request
↓
Intent Detection
↓
Capability Resolution
↓
Permission Check
↓
Connection Check
↓
Input Validation
↓
Policy Check
↓
Confirmation if Required
↓
Connector Execution
↓
Output Validation
↓
Audit Log
```

No step may be skipped.

---

# 10. Read vs Write Capabilities

Classify capabilities:

```text
read
write
destructive
restricted
```

Examples:

### Read

```text
calendar.read_events
storage.search_files
```

### Write

```text
calendar.create_event
mail.create_draft
```

### Destructive

```text
calendar.delete_event
storage.delete_file
```

AI behavior must follow existing F9 action confirmation policies.

Read operations may execute automatically only if policy permits.

Write/destructive operations require appropriate confirmation.

---

# 11. AI Connector Tool Registry

Expose approved connector capabilities as AI tools.

Example:

```text
Tool:
external.calendar.read_events
```

Tool metadata:

- Description
- Input schema
- Required permissions
- Connector dependency
- Connection requirement
- Safety classification

AI sees only tools available to the current user/company.

Do not expose every connector capability to every AI session.

---

# 12. Connection Authorization

A user must not automatically gain access merely because a company connection exists.

Runtime access requires:

```text
Company Connection Available
+
User Integration Permission
+
Capability Permission
+
Underlying Business Policy
```

Where integrations represent user-specific accounts, enforce account ownership rules.

---

# 13. Generic REST API Connector

Build a **restricted Generic REST Connector**.

This must NOT allow users or AI to freely execute arbitrary HTTP requests.

Instead administrators define:

```text
Approved Base URL
Approved Endpoints
Allowed HTTP Methods
Input Schema
Output Schema
Authentication Method
Rate Limits
Safety Classification
```

Example:

```text
Base:
https://api.example.com

Allowed Endpoint:
GET /inventory/status

Allowed Parameters:
item_id
warehouse_id
```

AI can call only registered endpoints.

No arbitrary URL construction.

No arbitrary headers.

No arbitrary request bodies outside schema.

---

# 14. Webhook Framework

Support controlled outbound webhooks.

Create:

```text
AI → Integrations → Webhooks
```

Webhook definition:

- Name
- Company
- Approved destination
- Event source
- Payload schema
- Secret/signature
- Status
- Retry policy

Events may include:

```text
workflow.completed
workflow.failed
task.created
ai.finding.created
```

Do not allow arbitrary event payloads containing all ERP data.

Use explicit payload schemas.

---

# 15. Webhook Security

Mandatory:

- HTTPS only unless explicit internal policy permits otherwise
- Destination allowlist
- Signed payload
- Secret rotation
- Timeout
- Retry limits
- Failure logging
- SSRF protection
- Block private/internal destinations unless explicitly approved

Do not allow webhook URLs that can access arbitrary internal network resources.

---

# 16. Incoming Webhooks

Architecture should support future incoming webhooks.

For this phase, build the framework only if practical.

Requirements:

- Unique endpoint token
- Signature verification
- Replay protection
- Rate limits
- Payload size limits
- Schema validation
- Audit logging

Do not automatically convert incoming webhook payloads into ERP actions.

Incoming data should create controlled events for later workflows.

---

# 17. Sync Jobs

Create reusable synchronization architecture.

Example:

```text
External CRM
↓
Sync Job
↓
Validate
↓
Map
↓
Preview
↓
Apply
```

Job types:

```text
manual
scheduled
event_triggered
```

Statuses:

```text
queued
running
completed
failed
partial
cancelled
```

Large synchronization must run asynchronously.

---

# 18. Data Mapping

Create controlled mapping definitions.

Example:

```text
External Field
supplier.company_name
        ↓
ERP Field
suppliers.name
```

Mapping should support:

- Direct mapping
- Controlled transformation
- Default values
- Required fields
- Validation

Do not allow arbitrary executable transformation scripts.

---

# 19. Sync Preview

Before applying external data:

Show:

```text
New Records
Updated Records
Skipped Records
Conflicts
Errors
```

For write operations:

- Require confirmation according to policy.
- Never silently bulk-modify ERP data.

---

# 20. Conflict Resolution

When external and ERP data conflict:

Statuses:

```text
external_newer
erp_newer
conflict
identical
```

Resolution strategies may include:

```text
manual_review
prefer_erp
prefer_external
```

AI may explain conflicts but must not silently choose a destructive resolution.

---

# 21. Integration Workflows

Integrate connectors with F18 Workflow Studio.

Example:

```text
Schedule Trigger
↓
Calendar Read Capability
↓
AI Summary
↓
Internal Notification
```

Workflow nodes may use connector capabilities only when:

- Connection exists
- Workflow policy allows it
- User/company authorization is valid
- Capability is registered

---

# 22. Integration Skills

Integrate with F17 Skills.

Example:

```text
Morning Meeting Assistant

1. Read Calendar
2. Read Open Tasks
3. Generate Summary
```

Skills cannot bypass connector capability permissions.

---

# 23. AI Command Center Integration

Users should be able to say:

> “Check tomorrow's calendar.”

The system:

```text
Detect Intent
↓
Find Registered Calendar Capability
↓
Check Connection
↓
Check Permission
↓
Execute Read
↓
Summarize
```

If no connection exists:

```text
Calendar is not connected.
Open Integration Setup?
```

Do not fabricate external data.

---

# 24. Connector Output Normalization

Normalize connector results before AI consumption.

Example:

```text
External API Response
↓
Connector Adapter
↓
Validated Normalized Output
↓
AI Context
```

Do not pass arbitrary raw external API responses directly into action pipelines.

---

# 25. External Data Trust Model

External data should be classified:

```text
unverified
validated
trusted_source
needs_review
```

AI summaries must understand source trust level.

Do not treat every API response as unquestionable ERP truth.

---

# 26. Connector Health Monitoring

Monitor:

- Authentication status
- API availability
- Last successful request
- Error rate
- Rate-limit events
- Token expiration

Statuses:

```text
healthy
degraded
failed
expired
disabled
```

Do not expose credentials in health logs.

---

# 27. Rate Limits

Each connector capability should support:

- Requests per minute
- Requests per hour
- Daily limits
- Concurrent requests

When exceeded:

- Queue safely if appropriate
- Reject with clear status
- Do not endlessly retry

---

# 28. Retry Policy

Retry only safe operations.

Potentially retryable:

- Read timeout
- Temporary 5xx error
- Temporary network failure

Never blindly retry:

- Non-idempotent writes
- Unknown partial writes
- Destructive operations

Store idempotency references where supported.

---

# 29. Circuit Breaker

Implement lightweight protection.

If connector repeatedly fails:

```text
healthy
↓
degraded
↓
temporarily_disabled
```

Prevent repeated AI/automation requests from flooding a failing service.

Allow administrator-controlled recovery/testing.

---

# 30. Integration Activity Log

Create:

```text
AI → Integrations → Activity
```

Record:

- Connector
- Connection
- Capability
- Actor/system
- Timestamp
- Status
- Duration
- Safe input summary
- Safe output summary
- Error category

Never log:

- API keys
- OAuth tokens
- Full sensitive payloads

---

# 31. Audit Logging

Audit:

```text
Connector Registered
Connection Created
Credential Updated
Capability Enabled
Connection Revoked
Webhook Created
Webhook Sent
Sync Started
Sync Applied
External Action Proposed
External Action Confirmed
```

Keep operational activity and security audit records appropriately separated.

---

# 32. Permissions

Add only if missing:

```text
ai.integrations.view
ai.integrations.manage
ai.integrations.connect
ai.integrations.disconnect
ai.integrations.use
ai.integrations.webhooks.manage
ai.integrations.sync
```

Capability-level permissions should remain independently enforceable.

Example:

```text
ai.integration.calendar.read
ai.integration.calendar.write
```

Only create granular permissions where architecture requires them.

---

# 33. Integration Policies

Add governance policies:

```text
Allow External Data Access
Allow External Writes
Allow Webhooks
Allow Generic REST Connectors
Allow OAuth Connections
Allow AI Connector Usage
```

Company administrators can restrict categories.

Policies must be enforced server-side.

---

# 34. Data Privacy Controls

Before external transmission check:

```text
Company Policy
User Permission
Connector Policy
Data Classification
Provider/External Destination
```

Support optional masking of:

- Personal data
- Financial data
- Confidential fields

Do not send full ERP records when a minimal subset is sufficient.

---

# 35. Sensitive Integrations

Allow connection classification:

```text
normal
sensitive
restricted
```

Restricted connections may require:

- Administrator approval
- Explicit user confirmation
- Additional audit logging
- Reduced AI autonomy

---

# 36. Management Center Integration

F16 should display:

- Active connectors
- Active connections
- Failed connections
- API errors
- Sync jobs
- Webhook failures
- External usage
- Estimated costs where available
- Policy violations

Administrators see governance metadata without automatically receiving external secrets.

---

# 37. Kill Switches

Add server-side controls:

```text
Disable All External Integrations
Disable AI Connector Tools
Disable External Writes
Disable Webhooks
Disable Sync Jobs
Disable Generic REST Connector
```

When disabled:

- New executions stop.
- Existing credentials remain secure.
- ERP core remains unaffected.

---

# 38. Initial Representative Connectors

Implement the framework plus **at least two representative connector types** appropriate for current architecture.

Recommended:

### A. Generic REST Connector

For controlled external APIs.

### B. Webhook Connector

For outbound event delivery.

Optional if existing credentials/OAuth architecture is mature:

### C. Calendar Connector

Read-only first.

Do not spend this phase implementing dozens of provider-specific connectors.

The priority is a robust reusable framework.

---

# 39. Security Requirements

Mandatory:

- Registered connectors only
- Registered capabilities only
- No arbitrary HTTP execution
- No arbitrary URLs
- No arbitrary headers
- Input/output schema validation
- Credential encryption
- Tenant isolation
- Permission checks
- Action confirmation
- SSRF protection
- HTTPS enforcement
- Rate limits
- Circuit breaker
- Safe retries
- Audit logging
- Server-side kill switches

---

# Important Constraints

Do NOT implement:

- Unrestricted Zapier-style arbitrary integrations
- AI-generated executable connector code
- Arbitrary HTTP requests from prompts
- Arbitrary webhook destinations
- Plaintext credential storage
- Automatic bulk ERP updates
- Automatic destructive sync conflict resolution
- Automatic external writes without policy/confirmation
- Arbitrary transformation scripts

This step is:

**A secure integration framework where AI can use explicitly registered external capabilities without becoming an unrestricted API execution engine.**

---

# Completion Criteria

AI F22 is complete when:

- Integration Center exists.
- Connector registry exists.
- Connection management works.
- Credentials are securely stored.
- Authentication abstraction exists.
- Capability registry works.
- AI can use registered connector capabilities.
- Generic REST connector is restricted and works.
- Outbound webhooks work securely.
- Sync job architecture works.
- Data mapping works.
- Sync preview works.
- Conflict handling works.
- Connector health monitoring works.
- Rate limits work.
- Retry policy works.
- Circuit breaker works.
- Permissions work.
- Privacy policies work.
- Workflow integration works.
- Skills integration works.
- F16 governance works.
- Kill switches work.

After implementation, provide:

- Files created
- Files modified
- Database changes
- Connector architecture
- Credential security approach
- Authentication abstraction
- Capability registry
- Generic REST restrictions
- Webhook security model
- Sync architecture
- Data mapping approach
- Conflict strategy
- Retry/circuit breaker design
- Permission model
- Privacy controls
- Security controls
- Known limitations