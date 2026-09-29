Implement **AI F8 — AI Tool Framework & Safe Read-Only ERP Access** in the existing Core PHP + MySQL ERP.

### Objective

Give the AI Assistant its first real ability to interact with ERP data through a controlled tool framework.

This step introduces:

- Generic AI tool architecture
- Tool registry
- Tool permission validation
- Tool input validation
- Read-only ERP tools
- Secure tool execution
- AI tool-call lifecycle

The AI must be able to request approved tools, but must NOT directly access the database.

All ERP data access must happen through server-side registered tools.

This phase is strictly **READ-ONLY**.

Do NOT allow create, update, delete, approve, submit, send, or any other state-changing operation.

---

# 1. Create Central AI Tool Framework

Create a reusable AI Tool Framework.

Architecture:

```text
AI Assistant
      ↓
Provider / Model
      ↓
Tool Request
      ↓
AI Tool Executor
      ↓
Tool Registry
      ↓
Permission Validation
      ↓
Input Validation
      ↓
Approved Tool
      ↓
ERP Service / Repository
      ↓
Read-only Result
      ↓
AI Response
```

The AI must never:

- Execute SQL directly
- Call arbitrary PHP functions
- Access arbitrary endpoints
- Access files directly
- Construct arbitrary database queries

Only explicitly registered tools may execute.

---

# 2. Tool Registry

Create a centralized server-side tool registry.

Each tool should define metadata such as:

- unique tool ID/name
- display name
- description
- module
- risk level
- action type
- required permissions
- input schema
- output schema/normalization
- enabled status

Conceptual example:

```php
[
    'id' => 'procurement.search_suppliers',
    'name' => 'search_suppliers',
    'description' => 'Search suppliers the current user is authorized to view.',
    'module' => 'procurement',
    'risk_level' => 'read_only',
    'required_permissions' => [
        'procurement.suppliers.view'
    ],
    'input_schema' => [
        // controlled schema
    ]
]
```

Adapt permission names to the actual ERP permissions.

Do not invent permissions if existing permissions already cover the feature.

---

# 3. Tool Contract

Create a common PHP tool interface/base contract.

Every tool should implement a consistent lifecycle such as:

```text
getDefinition()
validateInput()
authorize()
execute()
normalizeOutput()
```

Do not force unnecessary framework complexity.

Keep it Core PHP compatible.

Each tool must independently enforce:

- Input validation
- Authentication context
- Company/tenant scope
- Permission checks
- Read-only behavior

Do not rely only on the central executor.

---

# 4. Tool Categories

Create a registry structure that supports future categories:

- Read
- Write
- Action
- External
- System

For AI F8, implement only:

```text
Read
```

Every currently executable tool must explicitly be marked:

```text
read_only
```

The executor must reject:

- write
- action
- external
- system

tools unless explicitly enabled in future phases.

---

# 5. Provider Tool-Calling Integration

Extend the provider abstraction so supported providers/models can receive tool definitions.

Add generic provider capabilities such as:

- supportsToolCalling()
- buildToolDefinitions()
- parseToolCalls()

Do not place provider-specific function-calling logic inside the AI Assistant controller.

Provider adapters should translate between:

```text
Generic ERP Tool Definition
        ↓
Provider Tool Schema
```

and:

```text
Provider Tool Call
        ↓
Generic AI Tool Call
```

Support graceful fallback for models that do not support native tool calling.

For non-tool-calling models:

- Do not execute tools based on arbitrary text.
- Do not parse unsafe pseudo-function calls.
- Simply inform the user that the selected model does not support ERP data tools, or use a future safe fallback architecture.

Do not implement unreliable regex-based tool execution from AI text.

---

# 6. Tool Execution Lifecycle

Implement the following lifecycle:

```text
1. User sends message
2. AI receives conversation + ERP context + available tools
3. AI requests approved tool
4. Backend validates requested tool ID
5. Backend validates model/provider capability
6. Backend loads tool definition
7. Backend validates user permissions
8. Backend validates tool input
9. Backend validates tenant/company scope
10. Tool executes read-only operation
11. Result is normalized and size-limited
12. Tool result is sent back to AI
13. AI generates final user-facing response
14. Final response is stored
```

The AI must not receive unrestricted raw database output.

---

# 7. Tool Result Controls

Every tool result must be normalized.

Apply:

- Maximum result count
- Maximum response size
- Field allowlisting
- Sensitive field exclusion
- Tenant/company filtering

Do not return:

- Password hashes
- API keys
- Authentication tokens
- Internal secrets
- Unnecessary audit metadata
- Hidden financial/security fields

Tool outputs should contain only data necessary for the requested task.

---

# 8. Initial Procurement Read-Only Tools

Implement only tools for real Procurement functionality that already exists in the ERP.

Do not invent database tables or business entities.

At minimum, where corresponding functionality exists:

### Supplier Search

Logical tool:

```text
procurement.search_suppliers
```

Allow AI to search suppliers by:

- name
- partial name
- code/reference if available

Return a limited normalized result set.

Example output fields:

- supplier ID/reference
- supplier name
- status

Only return fields the current user is authorized to view.

---

### Purchase Requisition Search

Logical tool:

```text
procurement.search_purchase_requisitions
```

Allow searching/filtering by available real fields such as:

- reference number
- status
- requester
- date range

Do not invent unsupported filters.

Return a limited normalized result set.

---

### Purchase Order Search

Logical tool:

```text
procurement.search_purchase_orders
```

Allow searching/filtering by real fields such as:

- PO reference
- supplier
- status
- date range

Return a limited normalized result set.

---

### Record Detail Tools

Where cleanly supported by existing Procurement architecture, implement read-only detail tools such as:

```text
procurement.get_supplier
procurement.get_purchase_requisition
procurement.get_purchase_order
```

Requirements:

- Input requires a valid record identifier.
- Verify the user has access.
- Verify tenant/company scope.
- Return an allowlisted summary.
- Do not return every database column automatically.

Only implement detail tools for entities that actually exist and have stable access patterns.

---

# 9. Tool Discovery for AI

When preparing the AI request:

- Determine the user's accessible tools.
- Include only enabled tools.
- Include only tools for which the user currently has required permissions.
- Include only tools compatible with the selected model/provider.

Do not expose a complete list of administrator-only tools to normal users.

Tool definitions sent to AI should be concise.

---

# 10. Dynamic Tool Availability

Tool availability should depend on:

- Current user
- Permissions
- Company/tenant
- Enabled modules
- Tool enabled status
- Provider/model capability

Create a reusable resolver:

```text
getAvailableToolsForUser()
```

Future modules must be able to register tools without modifying AI Assistant core logic.

---

# 11. Read-Only Enforcement

This is mandatory.

Implement defense in depth.

The tool executor must reject any tool that is not explicitly classified as:

```text
read_only
```

Do not trust tool metadata from the AI/provider.

The server-side registry is authoritative.

Additionally:

- Tool SQL must use SELECT-only operations.
- Do not call existing create/update/delete services.
- Do not trigger side effects.
- Do not send emails/notifications.
- Do not update "last viewed" fields unless existing architecture inherently does so outside tool execution.
- Do not perform approval workflows.

AI F8 must not modify any ERP business record.

---

# 12. Tool Call UI Transparency

Enhance the AI Assistant UI.

When AI uses a tool, show a subtle activity indicator such as:

```text
Searching purchase orders...
```

or:

```text
Looking up supplier information...
```

Do not expose:

- Raw SQL
- Internal class names
- API credentials
- Sensitive parameters

After completion, optionally show a compact summary:

```text
Found 3 matching purchase orders
```

Keep the interface understandable for non-technical users.

---

# 13. Tool Execution Error Handling

Handle safely:

- Invalid tool ID
- Tool disabled
- Permission denied
- Invalid parameters
- Record not found
- Record access denied
- Database failure
- Provider tool-call parsing failure
- Timeout

Do not expose raw stack traces.

The AI should receive a normalized safe failure result where appropriate, so it can respond naturally without inventing data.

Example:

```text
The requested purchase order could not be found or is not accessible.
```

Do not reveal whether inaccessible records exist.

---

# 14. Tool Execution Logging

Integrate with existing audit/activity infrastructure.

Log:

- Tool requested
- Tool authorized
- Tool executed
- Tool failed
- Tool denied

Log metadata such as:

- user ID
- conversation ID
- tool ID
- timestamp
- success/failure
- duration

Do NOT log:

- API keys
- secrets
- full unrestricted record data
- raw provider authorization headers

Sensitive tool input should be minimized/masked where appropriate.

---

# 15. Conversation Integration

Tool usage should work within a normal conversation.

Example:

```text
User:
Show me recent purchase orders for ABC Supplies.

AI:
→ requests procurement.search_purchase_orders

System:
→ validates and executes tool

Tool Result:
→ returns allowed PO summaries

AI:
I found 4 recent purchase orders for ABC Supplies...
```

Persist the final assistant answer normally.

If tool-call messages need internal persistence for conversation continuity:

- Store them in a controlled metadata structure.
- Do not expose raw internal messages directly in the user conversation UI.
- Do not store secrets.

---

# 16. Context-Aware Tool Suggestions

Use AI F6 context where useful.

Example:

If user is currently viewing:

```text
Procurement → Purchase Order → PO-2026-001
```

and asks:

> Tell me about this.

The AI may use the registered:

```text
procurement.get_purchase_order
```

tool using validated context.

Important:

The backend must validate the record ID and access again.

Never blindly execute based solely on frontend context.

---

# 17. Permission Enforcement

Tool access must use the actual ERP permission system.

Checks must occur:

1. When building available tools.
2. Before tool execution.
3. Inside sensitive tools where appropriate.

Do not create an AI permission bypass.

A user who cannot normally view purchase orders must not be able to retrieve them through AI.

---

# 18. Rate and Resource Limits

Add sensible safeguards:

- Maximum tool calls per AI request
- Maximum execution time
- Maximum records returned
- Maximum output size
- Prevent recursive/unbounded tool loops

Recommended initial behavior:

- Configurable maximum tool-call iterations.
- Stop safely when limit is reached.
- Return a controlled failure to the AI.

Do not allow unlimited autonomous loops.

---

# Important Constraints

Do NOT implement:

- Create tools
- Update tools
- Delete tools
- Approval tools
- Submit tools
- Send email tools
- External web tools
- Autonomous agents
- Background task execution
- RAG
- Unrestricted database querying
- Arbitrary SQL generation/execution

This step is strictly:

**Secure AI Tool Framework + Read-Only ERP Data Access**

---

# Completion Criteria

AI F8 is complete when:

- Central AI Tool Registry exists.
- Tool contract exists.
- Tool executor validates every request.
- Only registered tools can execute.
- Only read-only tools can execute.
- Tool permissions are enforced.
- Tenant/company scope is enforced.
- AI can call supported Procurement read-only tools.
- Tool results are normalized and limited.
- AI can answer using real ERP data.
- Tool activity is transparently shown in UI.
- Tool usage is logged safely.
- Users cannot bypass ERP permissions through AI.
- No ERP record can be modified.

After implementation, provide:

- Files created
- Files modified
- Tool framework architecture
- Tools implemented
- Permission mappings
- Security controls
- Known limitations