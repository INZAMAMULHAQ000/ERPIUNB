Implement **AI F16 — AI Management Center, Governance & Observability** in the existing **Core PHP + MySQL ERP**.

### Objective

Now that the AI module contains providers, models, tools, actions, tasks, knowledge, agents, automations, insights, and the universal Command Center, build a centralized administration and governance layer.

This phase should give authorized administrators full visibility and control over the ERP AI ecosystem.

The goal is:

```text
AI Management Center
        ↓
Configuration
Governance
Monitoring
Usage
Safety
Auditability
Health
```

This is NOT another AI capability for end users.

This is the operational control center for the entire AI system.

---

# 1. Central AI Management Center

Create:

```text
AI → Management Center
```

The page should be accessible only to authorized administrators.

Create a dashboard containing high-level cards such as:

- AI System Status
- Active Providers
- Available Models
- Tool Calls
- Agent Runs
- Automation Runs
- Active Insights
- Knowledge Sources
- Failed Operations
- Usage Today

Use real data.

Do not display fake/demo statistics.

---

# 2. Management Center Navigation

Create a structured administration menu.

Suggested sections:

```text
Overview

Providers & Models
├── Providers
├── Models
└── Capability Testing

AI Capabilities
├── Tools
├── Actions
├── Agents
├── Automations
└── Insights

Knowledge
├── Collections
├── Sources
└── Processing

Operations
├── Command History
├── Agent Runs
├── Automation Runs
├── Pending Actions
└── Failed Operations

Governance
├── Permissions
├── Safety Policies
├── Usage Limits
└── Audit Logs

System
├── Health
├── Diagnostics
└── Settings
```

Reuse existing pages where possible.

Do not duplicate entire interfaces unnecessarily.

The Management Center can link to specialized existing pages.

---

# 3. AI System Health Dashboard

Create centralized health monitoring.

Monitor components such as:

```text
AI Providers
Models
Tool Registry
Action Registry
Knowledge Retrieval
Embedding Providers
Agent Engine
Automation Scheduler
Event Trigger System
Insight Engine
Command Router
```

Each component should have a status:

```text
healthy
warning
degraded
unavailable
unknown
```

Example:

```text
OpenAI Provider        ✓ Healthy
Embedding Provider     ✓ Healthy
Automation Scheduler   ⚠ Delayed
Knowledge Retrieval    ✓ Healthy
Agent Engine           ✓ Healthy
```

Do not mark a component healthy merely because configuration exists.

Health should be based on meaningful checks where practical.

---

# 4. Provider Health Checks

Extend provider management with controlled health checks.

Each configured provider should support:

```text
Configuration Valid
Authentication Check
Model Availability Check
Capability Check
Recent Failure Rate
Last Successful Request
```

Do not continuously ping providers on every page load.

Use:

- On-demand health checks
- Cached recent health state
- Scheduled checks where appropriate

Never expose API keys.

---

# 5. Model Capability Matrix

Create a centralized model capability view.

Show each configured model and capabilities such as:

```text
Chat
Tool Calling
Structured Output
Embedding
Agent Capable
```

Example:

| Model | Chat | Tools | Structured | Embedding | Agent |
|---|---|---|---|---|---|
| Model A | ✓ | ✓ | ✓ | ✗ | ✓ |
| Model B | ✓ | ✗ | ✓ | ✓ | ✗ |

Capabilities must come from actual provider/model configuration.

Do not infer unsupported capabilities automatically.

---

# 6. AI Feature Dependency Mapping

Create a dependency system.

Example:

```text
Command Center
   ├── Chat Model
   ├── Tool Calling
   └── Context Engine

Agents
   ├── Tool Calling
   ├── Structured Output
   └── Agent Engine

Knowledge
   ├── Embedding Provider
   ├── Vector Storage
   └── Knowledge Tool

Automations
   ├── Scheduler
   └── AI Provider (optional)

Insights
   ├── Read Tools
   └── AI Provider (optional)
```

Use this to explain why a feature is unavailable.

Example:

> Procurement Agent is unavailable because no enabled model currently supports Tool Calling + Structured Output.

Do not expose internal stack traces.

---

# 7. Central Usage Tracking

Create a reusable AI Usage Tracking service.

Track where supported:

- Provider
- Model
- Feature
- User
- Company
- Request type
- Input tokens
- Output tokens
- Estimated cost
- Duration
- Success/failure
- Timestamp

Features may include:

```text
chat
command_center
tool_call
agent
knowledge_embedding
knowledge_search
automation
insight
```

Do not store API keys.

Do not store full prompts by default.

---

# 8. Usage Aggregation

Create aggregated statistics.

Allow views by:

- Today
- Last 7 days
- Last 30 days
- Custom date range

Group by:

- Provider
- Model
- Company
- User
- Feature
- Success/failure

Display:

```text
Total Requests
Successful Requests
Failed Requests
Average Duration
Estimated Token Usage
Estimated Cost
```

Use efficient aggregation.

Do not calculate expensive full-table scans on every page load.

---

# 9. AI Cost Controls

Create configurable cost/budget controls.

Possible limits:

```text
Per User Daily Limit
Per Company Daily Limit
Per Model Daily Limit
Monthly Company Budget
Maximum Cost Per Agent Run
Maximum Cost Per Automation Run
```

Where exact provider cost information is unavailable:

- Mark cost as estimated or unavailable.
- Do not fabricate precise billing.

When a configured budget limit is reached:

```text
Budget Limit Reached
```

The system should safely block or restrict new non-critical AI requests according to policy.

Do not interrupt already committed ERP write operations.

---

# 10. Rate Limiting

Create centralized AI rate limiting.

Support limits such as:

```text
Requests Per Minute Per User
Requests Per Hour Per User
Agent Runs Per Hour
Automation Runs Per Hour
Embedding Requests Per Minute
Tool Calls Per Agent Run
```

Rate limiting must be server-side.

Return user-friendly messages.

Example:

> AI request limit reached. Please try again shortly.

Do not expose internal rate-limit keys or infrastructure.

---

# 11. AI Safety Policy Engine

Create a centralized AI Safety Policy layer.

Policies should govern:

```text
Maximum Tool Calls
Maximum Agent Steps
Maximum Automation Runtime
Maximum Knowledge Context
Maximum Upload Size
Maximum Retrieval Results
Maximum Nested Execution
Maximum Concurrent Runs
Write Confirmation Requirements
```

Existing F8–F15 limits should be consolidated into a discoverable policy structure where practical.

Do not weaken existing hard limits.

---

# 12. Policy Hierarchy

Implement clear policy precedence.

Conceptual model:

```text
System Hard Limit
        ↓
Company Policy
        ↓
Feature Policy
        ↓
User Permission
        ↓
Runtime Request
```

A lower-level configuration must never override a stricter higher-level limit.

Example:

```text
System Max Agent Steps = 20
Company Config = 10
Request asks = 50

Effective Limit = 10
```

---

# 13. Permission Governance

Create an AI permission overview.

Show:

- AI roles
- Permissions
- Assigned users/roles
- Feature dependencies

Relevant permissions may include existing concepts:

```text
ai.chat.use
ai.command.use
ai.tools.use
ai.actions.request
ai.agents.run
ai.automation.manage
ai.insights.view
ai.knowledge.manage
ai.management.view
```

Use actual existing permission naming conventions.

Do not rename existing permissions unnecessarily.

---

# 14. AI Capability Access Matrix

Create an administrator view:

| Capability | Required Permission | Enabled | Provider Requirement |
|---|---|---|---|
| Command Center | ... | ✓ | Chat |
| Agents | ... | ✓ | Tool Calling |
| Knowledge | ... | ✓ | Embedding |
| Automations | ... | ✓ | Optional AI |
| Insights | ... | ✓ | Optional AI |

This should help administrators understand feature availability.

---

# 15. Kill Switches

Implement controlled emergency disable switches.

Administrators should be able to disable:

```text
AI Chat
Command Center
Tool Calling
Write Action Requests
Agents
Automations
Scheduled Automations
Event Automations
Knowledge Retrieval
AI Insights
```

Requirements:

- Server-side enforcement.
- Changes take effect quickly.
- Existing running operations reach safe checkpoints.
- Pending write confirmations cannot bypass disabled policies.
- Disable actions are audited.

Do not implement a browser-only toggle.

---

# 16. Graceful Degradation

Define fallback behavior when AI components fail.

Examples:

### Chat Provider Unavailable

```text
AI conversation unavailable
```

### Embedding Provider Unavailable

```text
Existing indexed knowledge may still be searchable if query embeddings are available.
New indexing is unavailable.
```

### AI Narrative Unavailable

```text
Rule-based Insights continue using deterministic summaries.
```

### Agent Model Unavailable

```text
Agent cannot start.
Normal read-only ERP functions remain available.
```

The ERP itself must remain operational when AI is disabled.

---

# 17. Unified Error Monitoring

Create an AI operation error registry.

Track:

- Feature
- Operation type
- Error category
- Provider/model
- Company
- User where appropriate
- Timestamp
- Retry count
- Resolution status

Categories:

```text
provider_error
timeout
permission_denied
validation_error
limit_reached
configuration_error
tool_error
scheduler_error
unknown
```

Do not expose raw secrets or stack traces to normal users.

Administrators may see sanitized diagnostic information.

---

# 18. Failed Operations Center

Create:

```text
AI → Management → Failed Operations
```

Allow administrators to view:

- Failed command executions
- Failed agent runs
- Failed automation runs
- Failed insight evaluations
- Failed knowledge processing
- Provider failures

Features:

- Filter by feature
- Filter by date
- Filter by provider
- Filter by error category
- View safe diagnostics
- Retry only where operation type supports safe retry

Do not allow unsafe automatic replay of write actions.

---

# 19. Safe Retry Management

Allow retries only for idempotent or safely restartable operations.

Examples potentially safe:

- Knowledge extraction
- Embedding generation
- Read-only automation
- Insight evaluation
- Provider health check

Examples requiring special handling:

- Pending write actions
- Partially completed agent actions
- ERP record modifications

Never blindly replay state-changing operations.

---

# 20. AI Audit Timeline

Create a unified AI audit timeline.

Include major events:

```text
Provider Configured
Model Enabled
Tool Executed
Action Requested
Action Confirmed
Agent Started
Agent Completed
Automation Triggered
Knowledge Indexed
Insight Created
Safety Policy Changed
Kill Switch Activated
```

Allow filtering by:

- User
- Company
- Feature
- Date
- Operation
- Status

Reuse existing audit records where possible.

Avoid duplicating the same event across multiple tables unless necessary.

---

# 21. Sensitive Data & Prompt Governance

Add configurable AI data-handling policies.

Examples:

```text
Allow ERP Data in AI Context
Allow Company Knowledge in AI Context
Maximum Data Records Per Request
Mask Sensitive Fields
Prompt Retention Period
Conversation Retention Period
Usage Metadata Retention
```

Do not automatically send every ERP field to providers.

Integrate with existing tool schemas and context controls.

---

# 22. Sensitive Field Masking

Create an extensible data masking layer.

Architecture should support:

```text
field classification
        ↓
masking policy
        ↓
AI context transformation
```

Example policies:

```text
none
partial
full
exclude
```

Do not hardcode sensitive field names globally without considering existing data architecture.

The masking service should be reusable by:

- Tools
- Agents
- Command Center
- Insights
- Knowledge processing where appropriate

---

# 23. Prompt & Conversation Retention

Create retention settings.

Support configurable retention for:

```text
Conversation History
Command History
Agent Working Memory
Usage Metadata
Error Logs
Knowledge Processing Logs
```

Provide cleanup jobs compatible with Core PHP + scheduler architecture.

Deletion should be safe and auditable.

Do not delete ERP audit records required by policy.

---

# 24. AI Diagnostics

Create:

```text
AI → Management → Diagnostics
```

Provide safe diagnostic checks:

```text
Database Connectivity
Provider Configuration
Enabled Models
Tool Registry
Action Registry
Agent Registry
Knowledge Collections
Vector Storage
Scheduler Status
Event System
Insight Engine
Permissions
```

Allow administrators to run checks manually.

Diagnostics must not:

- Send real ERP write actions
- Expose secrets
- Modify production data unnecessarily

Use dry-run or read-only checks.

---

# 25. Scheduler Health Monitoring

Specifically monitor AI scheduler health.

Track:

- Last scheduler heartbeat
- Last successful cycle
- Last failure
- Number of due jobs
- Number of delayed jobs

Example:

```text
Scheduler Status: Warning

Last heartbeat: 12 minutes ago
Expected interval: 1 minute
```

Do not require constant polling.

Update heartbeat during legitimate scheduler cycles.

---

# 26. Configuration Validation

Before enabling critical AI features, validate dependencies.

Examples:

### Enable Agents

Verify:

- Compatible model exists
- Tool registry available
- Agent limits configured

### Enable Knowledge

Verify:

- Embedding provider configured
- Vector storage available

### Enable Scheduled Automation

Verify:

- Scheduler configured
- Automation safety policies enabled

If dependencies fail:

- Prevent unsafe enablement.
- Show actionable configuration requirements.

---

# 27. Configuration Change Audit

Every critical AI configuration change must be audited.

Examples:

- Provider added
- Provider disabled
- Model enabled
- Budget changed
- Rate limit changed
- Safety policy changed
- Kill switch activated
- Retention changed

Store:

- Who
- What changed
- Previous value summary
- New value summary
- Timestamp

Never store secrets such as API keys in audit history.

---

# 28. Management Dashboard Alerts

Show administrators operational alerts.

Examples:

```text
⚠ AI Provider Failure Rate Increased
⚠ Scheduler Has Not Run Recently
⚠ Knowledge Indexing Queue Has Failures
⚠ Company AI Budget Near Limit
✓ All AI Systems Healthy
```

Alerts must come from actual operational state.

Do not use AI-generated guesses for infrastructure health.

---

# 29. System-Wide AI Settings

Create centralized settings where not already implemented.

Suggested categories:

```text
General
Providers
Usage Limits
Safety Limits
Data Handling
Retention
Scheduler
Diagnostics
```

Do not move stable existing settings unnecessarily.

Prefer linking existing settings into the Management Center.

---

# 30. Management Center API Boundaries

If the ERP uses AJAX/API endpoints:

Ensure all management operations require:

- Authentication
- Administrator authorization
- CSRF protection where applicable
- Input validation
- Audit logging

Never expose:

- Provider API keys
- Full secret configuration
- Raw environment variables
- Database credentials

---

# 31. Security Requirements

Mandatory:

- Management Center admin-only.
- Server-side authorization.
- Provider secrets never returned to frontend.
- Kill switches server-enforced.
- Usage data tenant-scoped where applicable.
- Cost controls cannot be bypassed from client.
- Rate limits server-enforced.
- Configuration changes audited.
- Sensitive data policies applied before provider requests.
- Retention cleanup controlled.
- Safe diagnostics only.
- Retry protection for write operations.
- No raw stack traces to users.

---

# Important Constraints

Do NOT implement:

- Automatic provider credential discovery
- Automatic key scraping
- AI-generated infrastructure changes
- Automatic production database repair
- Automatic unsafe retries
- Browser-only safety controls
- Hidden bypasses around kill switches
- Storing API keys in logs/audits
- Full prompt logging by default

This step is:

**Centralized AI Governance, Operations, Safety, Cost Control & Observability.**

---

# Completion Criteria

AI F16 is complete when:

- AI Management Center exists.
- System health dashboard works.
- Provider/model health is visible.
- Capability dependencies are visible.
- Usage tracking works.
- Cost controls work where data is available.
- Rate limits work.
- Safety policies are centralized.
- Kill switches work server-side.
- Failed Operations Center works.
- Safe diagnostics work.
- Scheduler health is monitored.
- Retention policies work.
- Sensitive data masking architecture exists.
- Critical configuration changes are audited.
- ERP remains operational if AI is disabled.

After implementation, provide:

- Files created
- Files modified
- Database changes
- Management Center sections
- Health checks implemented
- Usage tracking approach
- Cost estimation approach
- Rate-limit architecture
- Safety policy hierarchy
- Kill switches
- Retention policies
- Sensitive-data handling approach
- Security controls
- Known limitations