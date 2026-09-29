Implement **AI F6 — ERP Context Engine** in the existing Core PHP + MySQL ERP.

### Objective

Make the AI Assistant aware of the user's current ERP environment without yet giving it permission to modify anything.

The AI should begin understanding context such as:

- Who is asking
- Which company/tenant they are working in
- Which ERP module they are currently using
- Which page they are viewing
- Which record they are currently viewing, where applicable
- What permissions they have

This step is strictly **context awareness**.

Do NOT implement ERP actions, tool execution, autonomous tasks, or record modifications.

---

# 1. Create Central AI Context Service

Create a reusable AI Context Service following existing Core PHP architecture.

It should be responsible for building a normalized context object for the current AI request.

Conceptually include:

```text
User Context
Company/Tenant Context
Module Context
Page Context
Record Context
Permission Context
Session Context
```

The context service must be reusable by future:

- AI Assistant
- AI Agents
- AI Tools
- Automation
- Knowledge Retrieval

Do not duplicate context-building logic across pages.

---

# 2. Define Generic ERP Context Structure

Create a standardized PHP array/object structure conceptually similar to:

```php
[
    'user' => [
        'id' => null,
        'name' => null,
    ],

    'company' => [
        'id' => null,
        'name' => null,
    ],

    'location' => [
        'module' => null,
        'page' => null,
        'route' => null,
    ],

    'record' => [
        'type' => null,
        'id' => null,
        'reference' => null,
    ],

    'permissions' => [],

    'metadata' => [],
]
```

Adapt names and structure to existing ERP conventions.

Important:

- Do not automatically include sensitive user data.
- Do not expose passwords, tokens, API keys, session IDs, or authentication secrets.
- Only include data useful for AI reasoning.

---

# 3. Frontend Page Context Detection

Implement a lightweight JavaScript mechanism that can provide the current ERP page context to the AI Assistant.

The frontend should be able to identify, where applicable:

- Current module
- Current page
- Current route/URL
- Current record type
- Current record ID
- Current record reference

Do not hardcode Procurement-specific logic inside the generic context engine.

Instead, create a reusable page context registration mechanism.

Conceptually, ERP pages/modules should be able to declare context metadata such as:

```javascript
AIContext.setPageContext({
    module: 'procurement',
    page: 'purchase_order_view',
    record: {
        type: 'purchase_order',
        id: 123,
        reference: 'PO-2026-001'
    }
});
```

This must be optional.

Pages without record context should still work.

---

# 4. Context Transport

When a user sends a message through the AI Assistant:

- Collect the current registered page context.
- Send only normalized context metadata to the backend.
- Never trust client-supplied context blindly.
- Backend must validate/sanitize context.
- Backend should verify user access where applicable.

The frontend context should assist the backend but must not become a security boundary.

Do not send:

- Entire page HTML
- Entire DOM
- Hidden fields
- API credentials
- Session cookies
- Authentication tokens

---

# 5. Backend Context Validation

When receiving page context:

- Validate expected fields.
- Validate module/page identifiers.
- Validate record ID format.
- Validate record ownership/company scope where possible.
- Do not allow arbitrary injected context structures.
- Reject or sanitize invalid values.

For record references:

The backend should verify that the current user is allowed to access the referenced record before including meaningful record metadata in AI context.

Do not allow a user to claim:

> "I am viewing PO ID 500"

and have the system blindly expose that record to the AI if they do not have access.

---

# 6. Current User Context

Build user context from the authenticated ERP session.

Include only useful information such as:

- User ID
- Display name
- Role names where appropriate
- Company/tenant scope

Do not include:

- Password hashes
- Email unless genuinely required
- Authentication/session secrets
- API credentials
- Other unnecessary sensitive information

Do not trust frontend user identity.

Always derive identity from the authenticated backend session.

---

# 7. Permission Context

Create a normalized list of relevant AI/ERP permissions available to the current user.

This is preparation for future tool execution.

For this phase:

- AI may know which capabilities the user is authorized to access.
- AI must NOT execute any action.

Avoid sending an excessively large raw permission dump if the ERP has many permissions.

Create a sensible normalized representation.

---

# 8. Module Context Registry

Create a central registry mechanism for ERP modules to describe themselves to the AI Context Engine.

Conceptually support:

- Module identifier
- Display name
- Description
- Relevant pages
- Context capabilities

Example concept:

```php
[
    'procurement' => [
        'name' => 'Procurement',
        'description' => 'Purchase requisitions, suppliers, RFQs and purchase orders',
        'pages' => [
            // registered pages
        ]
    ]
]
```

Do not manually integrate every ERP module yet.

For this phase:

- Create the registry architecture.
- Register the AI module itself.
- Register Procurement only if it can be done cleanly using existing structures without modifying business logic.

The architecture must support future modules.

---

# 9. Inject Context into AI Requests

Update AI F5 conversation request flow.

When sending a message:

```text
User Message
      +
ERP Context
      ↓
AI Request Builder
      ↓
System Instruction + Context
      ↓
Provider Adapter
```

The AI should receive context in a structured, clearly separated form.

Do not dump raw JSON without explanation.

Create a controlled system/context instruction format.

Example conceptual structure:

```text
Current ERP Context:

User:
- Name: ...
- Role: ...

Current Location:
- Module: Procurement
- Page: Purchase Order

Current Record:
- Type: Purchase Order
- Reference: PO-2026-001

Important:
This context describes the user's current ERP environment.
Do not claim to have accessed or modified data beyond the provided context.
```

Keep context separate from conversation history.

---

# 10. Context Transparency

Add a subtle UI indicator in the AI Assistant showing when page context is attached.

Example concept:

```text
Context active:
Procurement → Purchase Order → PO-2026-001
```

Requirements:

- User can see that context is being used.
- Do not expose sensitive internal IDs unnecessarily.
- Context indicator should update when page context changes.
- Keep UI minimal and consistent with existing ERP design.

Do not create complex context editing UI yet.

---

# 11. Context-Aware Conversation Behavior

Test scenarios should support questions like:

User is viewing:

```text
Procurement → Purchase Order → PO-2026-001
```

Then asks:

> What can you tell me about this purchase order?

At this phase, the AI should understand what "this purchase order" refers to based on provided context.

Important:

The AI only knows metadata/context currently supplied.

It must not pretend to know database details that have not been provided.

Do not automatically load full record data yet.

That comes later through secure AI Tools.

---

# 12. Context Persistence Rules

Do not permanently store volatile page context as part of every conversation message unless necessary.

Current page context should primarily be request-specific.

A user may:

1. Discuss Procurement.
2. Navigate to Finance.
3. Continue the same conversation.

The AI should receive the current context for the new request.

Do not allow stale page context to incorrectly represent the user's current location.

If context snapshots are stored for debugging/audit purposes:

- Store minimal sanitized metadata.
- Never store secrets.
- Follow existing privacy/audit conventions.

---

# 13. Security Requirements

Mandatory:

- Backend derives user identity from session.
- Client context is validated.
- Record access is verified where applicable.
- Context is company/tenant scoped.
- No secrets included.
- No page HTML/DOM transmitted.
- No arbitrary frontend JSON injected into system instructions.
- Output remains XSS-safe.
- Existing AI permission checks remain active.

---

# Important Constraints

Do NOT implement:

- Database record reading tools.
- ERP search.
- ERP record modification.
- Function calling.
- Tool execution.
- Autonomous actions.
- RAG.
- Knowledge base.
- Agents.

This step only gives the AI awareness of the user's ERP environment.

---

# Completion Criteria

AI F6 is complete when:

- AI receives authenticated user context.
- AI receives current company/tenant context where applicable.
- AI receives normalized page/module context.
- Optional record context can be registered.
- Frontend context is validated server-side.
- Context appears transparently in the UI.
- AI requests include current ERP context.
- Context updates as user navigates.
- No sensitive data is included.
- No ERP data is automatically read beyond context metadata.
- No ERP actions can be executed.

After implementation, provide:

- Files created
- Files modified
- Context structure implemented
- Module registry changes
- Security/validation measures
- Known limitations