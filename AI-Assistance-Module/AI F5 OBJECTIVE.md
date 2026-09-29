Implement **AI F5 — AI Assistant Core & Conversation Management** in the existing Core PHP + MySQL ERP.

### Objective

Build the first usable version of the ERP AI Assistant at:

**AI → Assistant**

This step introduces:

- Conversation management
- Message persistence
- Model selection
- Basic chat interface
- Real provider-backed AI chat

This is a basic conversational assistant only.

Do NOT implement ERP actions, autonomous execution, tools, agents, RAG, or automation yet.

---

# 1. Database Structure

Create the required MySQL tables following existing ERP conventions.

## AI Conversations

Create a table logically equivalent to:

`ai_conversations`

Fields should include:

- id
- user_id
- company_id / tenant scope if applicable
- title
- provider_id
- model_id
- status
- created_at
- updated_at
- last_message_at

Suggested status:

- active
- archived

---

## AI Messages

Create a table logically equivalent to:

`ai_messages`

Fields should include:

- id
- conversation_id
- role
- content
- metadata_json (nullable)
- input_tokens (nullable)
- output_tokens (nullable)
- created_at

Supported roles:

- system
- user
- assistant

Requirements:

- Conversation/message relationships must be enforced according to existing DB conventions.
- Messages must be ordered reliably.
- Users must not access another user's conversations unless future permissions explicitly allow it.
- Use appropriate indexes.

Do not store provider API credentials in these tables.

---

# 2. Conversation Service Layer

Create reusable Core PHP services/functions consistent with the existing architecture.

Logical operations:

- createConversation()
- getConversationById()
- getUserConversations()
- updateConversationTitle()
- archiveConversation()
- deleteConversation()
- addMessage()
- getConversationMessages()

Use prepared statements.

All conversation access must enforce user/company scope.

---

# 3. AI Assistant Page

Replace the Assistant placeholder with a functional chat interface.

Layout should follow the existing ERP design system.

Recommended structure:

### Left Panel

Conversation history:

- New Chat button
- Search conversations
- Conversation list
- Active conversation indication
- Archive/delete actions

### Main Panel

- Conversation messages
- Empty welcome state
- Message composer
- Send button

### Header

Display:

- Selected provider
- Selected model
- New conversation action

Do not copy ChatGPT's UI exactly.

Make it feel native to the ERP.

---

# 4. Provider and Model Selection

Allow the user to select from available:

- Active providers
- Active models belonging to active providers

Rules:

- Only show models whose provider is active.
- Only show active models.
- Default provider/model should be preselected where configured.
- Validate the selected provider/model relationship server-side.
- A model cannot be used with another provider.

If no usable provider/model exists:

Show a clear setup message directing authorized users to:

**AI → Management Center → Providers**

and/or

**AI → Management Center → Models**

Do not expose setup controls to unauthorized users.

---

# 5. New Conversation

When user starts a new chat:

- Create a conversation record.
- Associate selected provider and model.
- Scope it to the logged-in user.
- Do not create unnecessary empty conversations if existing UX can avoid it.
- Default title may initially be:

`New Conversation`

The conversation title can be automatically improved later.

---

# 6. Send Message Flow

Implement this flow:

1. User enters message.
2. Frontend validates non-empty input.
3. Request is sent to protected PHP backend endpoint.
4. Backend validates:
   - Authentication
   - AI assistant permission
   - CSRF protection
   - Conversation ownership
   - Provider availability
   - Model availability
5. User message is stored.
6. Conversation history is loaded.
7. Provider adapter is resolved.
8. Generic chat request is created.
9. Provider adapter calls the AI model.
10. AI response is received.
11. Assistant message is stored.
12. Response is returned to frontend.
13. UI displays assistant response.

Do not implement streaming yet.

Use normal request/response completion.

---

# 7. Generic Chat Request

Use the provider abstraction architecture created earlier.

The Assistant must not directly contain OpenAI-specific request logic.

Convert stored conversation messages into a generic request format.

Conceptually:

```text id="0q63cf"
Conversation History
        ↓
Generic AI Chat Request
        ↓
Provider Adapter
        ↓
Provider-specific API request
        ↓
Generic AI Chat Response
```

Provider adapters are responsible for translating generic requests/responses.

---

# 8. System Prompt Foundation

Create a centralized ERP AI system instruction.

For this step, the assistant should understand that it is:

- The ERP AI Assistant
- Helpful and professional
- Focused on assisting ERP users
- Not authorized to claim actions were performed when it only generated text
- Not yet capable of modifying ERP records

The system instruction must clearly prevent false claims such as:

> "I created the purchase order."

unless actual execution infrastructure exists.

Keep the system prompt centralized and configurable in code.

Do not create database UI management for prompts yet.

---

# 9. Conversation History

When sending a message:

- Load relevant conversation messages in chronological order.
- Send conversation history to the provider.
- Maintain role structure correctly.
- Do not accidentally mix conversations.
- Apply a reasonable configurable message/history limit to prevent uncontrolled request size.

Do not implement advanced summarization yet.

If history must be limited:

Prefer retaining the most recent messages.

---

# 10. Message Rendering

Render messages safely.

Requirements:

- Escape unsafe HTML.
- Prevent XSS.
- Preserve readable formatting.
- Support paragraphs and line breaks.
- Do not execute HTML returned by AI.
- Do not trust provider output.

Markdown rendering may be added only if safely sanitized.

---

# 11. Loading and Error States

Implement clear UI states:

### Sending

- Disable duplicate send.
- Show typing/loading indicator.

### Success

- Display assistant response.

### Failure

- Preserve the user's message.
- Show safe error message.
- Do not expose API credentials.
- Do not expose raw provider internals.

Handle:

- Network timeout
- Provider unavailable
- Authentication failure
- Invalid model
- Generic server error

---

# 12. Conversation Actions

Implement:

### Rename

Allow user to rename their conversation.

### Archive

Allow archive.

Archived conversations should not appear in the default active list.

### Delete

Allow user to delete their own conversation.

Require confirmation according to existing ERP UX.

Deletion must enforce ownership server-side.

---

# 13. Conversation Search

Add conversation search.

Search within the logged-in user's accessible conversations.

Search by title.

Do not expose other users' conversation titles.

---

# 14. Permissions

Use:

```text id="j0ibcc"
ai.assistant.access
```

Server-side enforcement is mandatory for:

- Assistant page
- Conversation listing
- Conversation creation
- Sending messages
- Rename
- Archive
- Delete

Do not rely on hidden UI.

---

# 15. Audit and Logging

Log operational events where appropriate without storing unnecessary conversation content in audit logs.

Examples:

- AI conversation created
- Provider/model selected
- AI request succeeded
- AI request failed

Do not log:

- API keys
- Authorization headers
- Full provider request payloads unless explicitly required by secure debug settings

Conversation content already belongs in conversation storage and should not be duplicated unnecessarily into audit logs.

---

# 16. Security Requirements

Mandatory:

- Prepared SQL statements.
- Server-side authentication checks.
- Server-side conversation ownership checks.
- CSRF protection.
- XSS-safe message rendering.
- API keys remain server-side.
- Provider/model relationship validation.
- Rate limiting using existing ERP infrastructure if available.
- Request timeout.
- No raw provider errors exposed to users.

---

# Important Constraints

Do NOT implement:

- Streaming responses.
- ERP tools.
- Function calling.
- ERP record creation/update/delete.
- Autonomous execution.
- AI agents.
- RAG/knowledge retrieval.
- File uploads.
- Voice.
- Automation workflows.

This step is strictly:

**Conversational AI + Conversation Management**

---

# Completion Criteria

AI F5 is complete when:

- User can open AI Assistant.
- User can create conversations.
- User can select valid provider/model combinations.
- User can send a message.
- Real AI response is received through provider adapters.
- Conversation messages persist.
- Conversation history works.
- Conversations are user-scoped.
- Rename/archive/delete works.
- Search works.
- Unsafe HTML cannot execute.
- Errors are handled safely.
- No ERP records can be modified by AI.
- Existing ERP functionality remains unaffected.

After implementation, provide:

- Files created
- Files modified
- Database changes
- Endpoints created
- Security measures implemented
- Known limitations