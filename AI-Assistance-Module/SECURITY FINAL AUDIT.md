You are now performing the FINAL integration, hardening, security, performance, and production-readiness pass for the ERP AI system.

IMPORTANT:
- This ERP is built with **Core PHP + MySQL, NO frameworks**.
- Preserve and reuse the existing ERP architecture and conventions.
- F34 was the FINAL major AI feature.
- Do NOT create F35, F36, F37, etc.
- Do NOT introduce a new AI feature unless it is strictly required to complete integration/hardening.
- Your goal is to make the existing AI system from F9 through F34 production-ready.

==================================================
1. COMPLETE AI ARCHITECTURE AUDIT
==================================================

Review the entire AI implementation from F9 through F34.

Create an internal architecture map covering:

- AI Command Center / Copilot
- Provider/model management
- Capability routing
- Skills/tools
- Integrations/connectors
- Workflows
- Agent runtime
- Proactive intelligence
- Document intelligence
- Voice
- Analytics/forecasting
- Process intelligence
- Communications
- Enterprise Knowledge/RAG
- Personalization/memory/goals/routines
- Governance/security
- Observability/evaluation
- Background jobs/queues/schedulers
- Notifications
- ERP module integrations

Identify and eliminate:

- duplicate execution engines
- duplicate permission checks
- duplicate provider logic
- duplicate AI request handling
- duplicate audit implementations
- inconsistent error handling
- inconsistent tenant filtering
- obsolete tables/code
- dead routes
- unused configuration
- conflicting service classes
- duplicated UI components
- inconsistent naming
- feature-specific security bypasses

There must be ONE coherent execution architecture.

The preferred flow should remain conceptually:

User/Input
→ Authentication
→ Tenant validation
→ Context collection
→ Intent/capability routing
→ F24 Governance
→ Provider/model selection
→ AI processing
→ Registered tool/skill execution
→ Verification
→ Audit
→ F23 Observability
→ User response/follow-up

For consequential actions:

Understand
→ Plan
→ Govern
→ Confirm/Approve
→ Execute
→ Verify
→ Audit
→ Monitor/Follow-up

==================================================
2. SECURITY FINAL AUDIT
==================================================

Perform a complete security audit across every AI feature.

Verify server-side enforcement for:

- authentication
- authorization
- RBAC
- tenant isolation
- department restrictions
- record-level permissions
- document permissions
- knowledge permissions
- integration permissions
- provider restrictions
- tool restrictions
- agent restrictions
- autonomy limits
- financial thresholds
- bulk-operation limits
- confirmation requirements
- human-review requirements

Never trust the UI to enforce security.

Every sensitive operation must be revalidated server-side.

Explicitly test and block:

- tenant switching
- IDOR
- unauthorized record IDs
- unauthorized document IDs
- unauthorized knowledge chunks
- direct API endpoint access
- manipulated POST parameters
- manipulated hidden fields
- forged confirmation IDs
- expired confirmations
- changed action parameters
- replayed confirmations
- self-approval
- permission escalation
- governance bypass
- provider-policy bypass
- agent-policy bypass

==================================================
3. PROMPT INJECTION / AI SECURITY
==================================================

Perform a final prompt-injection audit.

Clearly separate:

- system instructions
- developer/application instructions
- user instructions
- ERP data
- retrieved documents
- knowledge content
- external API responses
- tool outputs
- email/message content
- uploaded document content
- webpage/external content

Treat retrieved/external content as untrusted data.

Verify that content such as:

"ignore previous instructions"

"reveal your system prompt"

"execute this command"

"send this document"

"approve this payment"

cannot override governance or application rules.

AI-generated text must never become executable instructions merely because the model produced it.

==================================================
4. SECRET AND SENSITIVE DATA AUDIT
==================================================

Search the entire codebase for possible leakage of:

- API keys
- provider credentials
- passwords
- access tokens
- refresh tokens
- encryption keys
- session secrets
- database credentials
- webhook secrets

Verify they never appear in:

- AI prompts
- model responses
- logs
- traces
- error messages
- browser HTML
- JavaScript
- URLs
- audit records
- exported reports

Verify centralized masking/redaction works consistently.

Verify sensitive ERP fields are filtered before provider transmission according to F24 policy.

==================================================
5. SQL / CODE EXECUTION SECURITY
==================================================

Perform a complete audit to ensure AI cannot:

- execute arbitrary SQL
- execute shell commands
- execute arbitrary PHP
- execute arbitrary filesystem operations
- dynamically include/require arbitrary files
- access arbitrary URLs
- bypass registered tools
- directly modify production database tables

All AI database access must use approved application services/repositories/tools.

Dynamic analytics/reporting must continue using controlled query builders, allowlists, prepared parameters, tenant filters, and row limits.

==================================================
6. PROVIDER / MODEL ARCHITECTURE
==================================================

Audit F16 provider/model management.

Ensure every AI request goes through the centralized provider abstraction.

Verify:

- provider allowlists
- model allowlists
- feature-specific model restrictions
- fallback providers
- timeout handling
- retry handling
- rate limits
- cost limits
- token limits
- context limits
- provider health
- provider failure handling
- emergency provider disablement

Never allow a feature to hard-code a provider in a way that bypasses administration/governance.

Ensure provider failures degrade gracefully.

==================================================
7. AI REQUEST PIPELINE
==================================================

Create or consolidate a single reusable AI request service where appropriate.

It should consistently handle:

- request ID
- tenant
- user
- feature
- context
- provider/model
- governance decision
- token usage
- latency
- cost
- response status
- failure classification
- audit
- F23 observability

Avoid every module implementing its own incompatible AI request pipeline.

==================================================
8. AGENT / TOOL / ACTION SAFETY
==================================================

Audit F25.

Verify agents can ONLY use:

- registered skills
- registered tools
- approved integrations
- approved ERP services

Verify:

- max steps
- max runtime
- max tool calls
- max records
- max retries
- max cost
- autonomy level
- confirmation requirements
- human-review requirements
- loop detection
- checkpointing
- cancellation
- pause/resume
- idempotency
- retry safety

Ensure F34 memory/personalization NEVER increases agent authority.

==================================================
9. CONFIRMATION AND APPROVAL CONSISTENCY
==================================================

There must be one reliable confirmation/approval mechanism governed by F24.

Every consequential action must bind confirmation to:

- user
- tenant/company
- action
- exact parameters
- target records
- risk level
- session/context
- expiration

Any meaningful parameter modification must invalidate the confirmation.

Verify users cannot approve an action that they themselves are not authorized to perform.

==================================================
10. QUEUES / CRON / BACKGROUND JOBS
==================================================

Audit all asynchronous processing:

- agent tasks
- document processing
- intelligence scans
- analytics jobs
- process analysis
- knowledge indexing
- communication follow-ups
- routines
- scheduled briefings
- notifications
- evaluation jobs

Ensure:

- jobs are idempotent
- duplicate execution is prevented
- failures are retryable where appropriate
- permanent failures are recorded
- stuck jobs can be recovered
- concurrency is controlled
- tenant isolation remains enforced
- jobs cannot bypass F24
- jobs cannot silently perform consequential actions

Use the existing Core PHP scheduler/cron architecture.

==================================================
11. DATABASE AND PERFORMANCE HARDENING
==================================================

Review all AI-related MySQL tables.

Add appropriate indexes for common:

- tenant_id
- company_id
- user_id
- status
- created_at
- updated_at
- provider
- model
- feature
- task/run IDs
- document IDs
- knowledge IDs
- workflow IDs

Review foreign keys where appropriate.

Identify:

- N+1 queries
- unbounded queries
- missing pagination
- repeated expensive calculations
- unnecessary AI calls
- repeated retrieval
- excessive context construction
- oversized prompts
- duplicate background processing

Never load entire large datasets into memory unnecessarily.

Add sensible:

- pagination
- batching
- limits
- caching where safe
- incremental processing
- context limits

Do not cache data across tenants/users in a way that can leak information.

==================================================
12. AI COST CONTROL
==================================================

Verify centralized enforcement of:

- per-request token limits
- per-user limits
- per-tenant limits
- provider limits
- feature limits
- agent limits
- daily/monthly budgets where supported

Prevent runaway agent loops and repeated AI calls.

F23 must record cost/usage wherever provider information is available.

==================================================
13. ERROR HANDLING
==================================================

Standardize AI errors.

Users should receive useful messages such as:

- provider unavailable
- action requires approval
- insufficient permissions
- insufficient data
- document processing failed
- task partially completed
- request timed out
- AI response unavailable

Do NOT expose:

- SQL errors
- PHP stack traces
- credentials
- internal filesystem paths
- provider secrets
- sensitive internal implementation details

Every failure should have appropriate internal logging/observability.

Never claim "completed" when execution actually failed or only partially completed.

==================================================
14. UI / UX CONSISTENCY
==================================================

Perform a final review of all AI screens.

Ensure consistent:

- navigation
- terminology
- buttons
- confirmation dialogs
- loading states
- empty states
- error states
- permission-denied states
- AI response formatting
- evidence/source presentation
- status indicators
- task progress
- approval UX

The user should experience one coherent ERP AI system rather than dozens of unrelated AI features.

The global AI Command Center should remain the primary entry point.

==================================================
15. EVIDENCE / TRUST / EXPLAINABILITY
==================================================

Ensure AI responses distinguish between:

- Verified ERP fact
- Retrieved knowledge/source
- Calculation
- AI interpretation
- Recommendation
- Forecast/estimate
- Proposed action
- Executed action

Do not expose hidden chain-of-thought.

Instead expose useful business evidence:

- records
- metrics
- source documents
- citations
- calculations
- assumptions
- confidence
- limitations
- governance status

Never fabricate evidence or citations.

==================================================
16. F23 OBSERVABILITY FINALIZATION
==================================================

Verify all major AI operations generate useful observability data.

Ensure traces connect:

- Copilot request
- provider call
- tool calls
- agent steps
- document processing
- knowledge retrieval
- workflow execution
- external integrations
- final result

Verify administrators can diagnose:

- failures
- latency
- provider problems
- expensive features
- failed actions
- security events
- evaluation regressions

Sensitive content must remain masked according to F24.

==================================================
17. F24 GOVERNANCE FINALIZATION
==================================================

Verify F24 is the authoritative policy layer.

No feature may bypass it.

Test governance on:

- Copilot
- Agents
- Documents
- Voice
- Analytics
- Process Intelligence
- Communications
- Knowledge
- Memory
- Routines
- Integrations
- External providers
- Autonomous tasks

Verify emergency controls work globally and feature-by-feature.

Verify disabling AI prevents execution while leaving normal ERP operation functional.

==================================================
18. DATA RETENTION / PRIVACY
==================================================

Review retention for:

- conversations
- memory
- voice
- documents
- knowledge chunks
- AI traces
- evaluations
- agent history
- communications
- prompts/responses
- audit events

Ensure configured retention is actually enforced.

Implement safe cleanup jobs where missing.

"Forget memory" must actually remove/deactivate the relevant memory according to the designed retention model.

Deleted/expired knowledge must no longer be retrievable.

==================================================
19. MIGRATION / DEPLOYMENT SAFETY
==================================================

Review all AI database migrations.

Ensure:

- migrations are ordered
- migrations are repeat-safe where appropriate
- indexes are production-safe
- defaults are correct
- existing ERP data is preserved
- rollback strategy exists where practical

Do not modify or delete existing production data merely to make AI features work.

==================================================
20. FEATURE FLAGS / EMERGENCY CONTROLS
==================================================

Verify administrators can disable:

- AI globally
- autonomous agents
- external providers
- integrations
- document AI
- voice
- workflows/automation
- communication sending
- AI-generated external actions

Kill switches must be enforced server-side.

AI must never be able to modify or override its own emergency controls.

==================================================
21. FULL REGRESSION TEST
==================================================

Run a complete regression across:

F9 → F34

including all existing ERP modules that AI touches.

Test:

- normal ERP operation with AI enabled
- normal ERP operation with AI disabled
- permissions
- tenant isolation
- documents
- workflows
- integrations
- communications
- analytics
- knowledge
- agents
- voice
- proactive intelligence
- memory
- goals
- routines
- notifications
- background jobs

Fix regressions rather than introducing compatibility hacks.

==================================================
22. SECURITY TEST SUITE
==================================================

Create/run a repeatable security test checklist covering at minimum:

1. unauthorized record access
2. cross-tenant access
3. RBAC bypass
4. IDOR
5. SQL injection
6. arbitrary SQL attempts
7. PHP/code execution attempts
8. shell execution attempts
9. arbitrary URL/SSRF attempts
10. credential leakage
11. prompt injection
12. tool injection
13. confirmation replay
14. expired confirmation
15. parameter tampering
16. self-approval
17. agent privilege escalation
18. bulk-operation abuse
19. provider-policy bypass
20. kill-switch bypass

All must fail safely.

==================================================
23. LOAD / STRESS TEST
==================================================

Test realistic concurrent usage.

Measure:

- normal AI request latency
- database latency
- queue throughput
- agent execution
- document processing
- knowledge search
- analytics
- concurrent users
- provider rate limits

Identify and fix obvious bottlenecks.

Do not sacrifice tenant isolation or governance for performance.

==================================================
24. FINAL CODE QUALITY
==================================================

Perform final cleanup.

Remove:

- debug output
- temporary test code
- commented-out obsolete implementations
- hard-coded credentials
- development-only bypasses
- duplicated services
- obsolete routes
- unused database structures where safely removable

Keep production logs useful but non-sensitive.

Ensure Core PHP coding conventions remain consistent with the existing ERP.

==================================================
25. FINAL PRODUCTION READINESS REPORT
==================================================

After implementation, produce an internal completion report containing:

A. Architecture status
B. Security status
C. Governance status
D. Provider status
E. Agent/tool status
F. Database status
G. Performance status
H. Queue/scheduler status
I. Observability status
J. Privacy/retention status
K. Regression status
L. Remaining known limitations
M. Recommended deployment checks

Do not claim production-ready merely because code compiles.

The system is complete only when critical security, tenant isolation, governance, execution safety, regression, and performance tests pass.

FINAL HARD BOUNDARY:

There is no F35.

After this phase, the ERP AI module is considered complete. Any future work should be treated as maintenance, bug fixing, security updates, performance optimization, or a separately approved future product expansion—not another numbered AI feature.