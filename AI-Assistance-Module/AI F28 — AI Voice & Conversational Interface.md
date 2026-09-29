# AI F28 — AI Voice & Conversational Interface

Build **AI F28 — AI Voice & Conversational Interface** for the existing ERP.

## NON-NEGOTIABLE TECH STACK

- **Core PHP + MySQL only**
- **No frameworks**
- Reuse the existing ERP architecture and existing AI features.
- Do not introduce Laravel, Symfony, CodeIgniter, React, Vue, Node.js, Python services, or another application framework.
- Reuse F16 provider management, F23 observability, F24 governance, F25 Agent Runtime, F26 Intelligence, and F27 Document Intelligence.

## OBJECTIVE

Allow ERP users to interact with the existing AI using **natural voice conversations**, while preserving exactly the same security, permissions, governance, audit, and execution controls as text interaction.

Examples:

> User speaks: "Show me today's overdue invoices."

> User speaks: "Create a follow-up task for the three highest-value customers."

> User speaks: "What's happening with our procurement this week?"

The voice layer is only an **input/output interface**. It must never create a separate privileged execution path.

---

## 1. VOICE ASSISTANT UI

Add a microphone control to the existing AI Command Center.

Support:

- Start recording
- Stop recording
- Cancel
- Processing indicator
- Transcription display
- AI response
- Speak response
- Conversation history

Clearly indicate when the microphone is actively recording.

Do not record continuously unless explicitly enabled by the user.

---

## 2. SPEECH-TO-TEXT PROVIDER

Create a provider abstraction for speech recognition.

Integrate with the existing F16 provider/model management where supported.

Store configuration for:

- provider
- model
- language
- timeout
- maximum recording duration
- enabled/disabled

Never hard-code provider credentials.

---

## 3. TEXT-TO-SPEECH PROVIDER

Create a provider abstraction for voice responses.

Support:

- provider
- voice
- language
- speed
- enabled/disabled
- maximum response length

If voice generation fails, display the normal text response.

---

## 4. CONVERSATIONAL CONTEXT

Voice conversations must use the same conversation/context architecture as text AI.

Preserve:

- conversation
- user
- tenant
- permissions
- current ERP context
- referenced records
- previous messages

Never transfer authorization through conversational context.

Authorization must always be checked server-side.

---

## 5. VOICE → ERP NAVIGATION

Support spoken navigation:

> "Open procurement."

> "Take me to overdue invoices."

> "Show supplier ABC."

The AI should return normal ERP navigation targets.

Verify the user has permission to access the destination.

Never use voice intent to bypass menu/module permissions.

---

## 6. VOICE → QUESTIONS

Support spoken questions such as:

> "How many purchase orders are overdue?"

> "What's our total outstanding receivables?"

> "Which supplier has the most delayed deliveries?"

Answers must use the same authorized data retrieval mechanisms as text AI.

No direct database access.

---

## 7. VOICE → ACTIONS

Allow spoken action requests:

> "Create a task for John to review this invoice."

> "Prepare a supplier follow-up."

> "Create a draft purchase request."

Route these through F25 Agent Runtime.

Every action must pass F24 governance.

Voice confirmation is not automatically equivalent to authorization for sensitive actions.

---

## 8. HIGH-RISK VOICE CONFIRMATION

For sensitive actions, require explicit confirmation.

Example:

> AI: "This will send an email to 12 suppliers. Do you want me to proceed?"

User:

> "Yes."

The confirmation must be bound to:

- user
- tenant
- exact action
- exact parameters
- expiration
- task/step

Any parameter change invalidates the confirmation.

For particularly sensitive actions, continue using the ERP's existing confirmation/review mechanism rather than relying solely on spoken "yes."

---

## 9. VOICE CLARIFICATION

If the request is ambiguous, the AI should ask:

> "Which supplier do you mean?"

> "Do you want this for this month or this quarter?"

> "Should I prepare the email or send it?"

Do not guess critical parameters when ambiguity could cause an incorrect business action.

---

## 10. MULTILINGUAL SUPPORT

Support multiple languages through provider configuration where available.

At minimum design the architecture so language can be configured per:

- user
- conversation
- provider
- organization

The ERP's internal data and commands must still be interpreted consistently.

---

## 11. NOISE / TRANSCRIPTION HANDLING

Handle:

- empty recordings
- poor transcription
- background noise
- unsupported language
- partial speech
- timeout
- provider failure

Show useful errors rather than executing uncertain commands.

Low-confidence transcription should not automatically trigger sensitive actions.

---

## 12. AUDIO PRIVACY

Audio should be processed according to F24 data policies.

Support configurable:

- retention
- delete-after-processing
- storage/no-storage mode
- external provider transmission
- sensitive-data restrictions

Default to minimizing retained audio.

Do not retain microphone recordings unnecessarily.

---

## 13. RECORDING INDICATORS

The UI must clearly show:

- microphone active
- recording duration
- processing
- speaking response
- stopped

The system must never give the impression that recording is active when it is not.

---

## 14. VOICE COMMAND SAFETY

Treat speech transcription as user input.

Apply the same protections against:

- prompt injection
- malicious instructions
- unauthorized actions
- parameter manipulation
- privilege escalation

Do not trust transcribed text simply because it came from the microphone.

---

## 15. SPEAKER IDENTITY

Do **not** use voice recognition as the sole authorization mechanism.

The logged-in ERP session remains authoritative.

If optional speaker recognition is implemented later, it must be treated as an additional signal, not a replacement for authentication/RBAC.

---

## 16. VOICE RESPONSE SAFETY

Before speaking a response, apply the same F24 data filtering/masking as text output.

Do not speak:

- passwords
- API keys
- session tokens
- encryption keys
- restricted information
- unauthorized record data

If the user lacks permission to see the information in text, they must also lack permission to hear it.

---

## 17. VOICE + F25 AGENTS

Allow:

**Voice → F25 Agent Task**

Example:

> "Review all overdue purchase orders and prepare supplier follow-ups."

Create the same structured F25 task that a text request would create.

Show progress in the UI.

The user can then:

- pause
- resume
- cancel
- approve
- review

using the existing controls.

---

## 18. VOICE + F26 INTELLIGENCE

Allow users to ask about proactive insights:

> "What needs my attention today?"

> "Any important procurement issues?"

> "What are the biggest financial risks?"

Return F26 intelligence using authorized data.

Allow:

> "Handle this."

to create an F25 task, subject to F24.

---

## 19. VOICE + F27 DOCUMENTS

Allow document-related questions:

> "What does this invoice say?"

> "What's the payment term?"

> "Does this invoice match the purchase order?"

Use F27's grounded document intelligence.

Do not expose restricted documents.

---

## 20. INTERRUPT / STOP

While the AI is speaking, allow:

**Stop**

The response audio should stop promptly.

For an executing F25 task, stopping speech must **not automatically cancel the task**.

Provide separate task cancellation controls.

---

## 21. VOICE HISTORY

Show conversation history containing:

- transcription
- AI response
- actions
- approvals
- task status

Allow users to delete conversation/audio according to retention policy.

Do not expose hidden system prompts or internal security policies.

---

## 22. F23 OBSERVABILITY

Record:

- voice request
- transcription processing
- provider/model
- latency
- token/cost data where available
- action/task generated
- errors
- user feedback

Do not unnecessarily store raw audio.

Respect existing retention and privacy controls.

---

## 23. F24 GOVERNANCE

Every voice interaction must pass the same governance path as text:

**Authentication → Tenant → Permissions → Data Policy → Risk → Tool/Action Policy → Provider Policy → Autonomy → Confirmation/Review → Execute → Audit**

Voice must never create a bypass.

---

## 24. F16 PROVIDER MANAGEMENT

Allow administrators to configure:

- speech-to-text provider
- text-to-speech provider
- language
- models
- voice
- limits
- cost controls
- enabled features

Provider credentials must remain encrypted and inaccessible to the model/user.

---

## 25. FAILURE FALLBACKS

If speech-to-text fails:

Offer:

**Type your request instead**

If text-to-speech fails:

Continue showing the response as text.

If an action fails:

Use the existing F25 failure/recovery mechanism.

Never silently retry dangerous actions.

---

## 26. DATABASE

Create normalized tables following existing ERP conventions where persistent storage is required.

Potential tables:

- ai_voice_sessions
- ai_voice_messages
- ai_voice_processing
- ai_voice_preferences

Include:

- tenant/company ID
- user ID
- conversation reference
- timestamps
- provider references
- status
- retention metadata
- audit fields

Do not store raw audio permanently unless explicitly required by policy.

---

## 27. PERFORMANCE

Implement:

- maximum recording duration
- upload limits
- provider timeout
- request cancellation
- asynchronous processing where appropriate
- graceful provider failure
- concurrency limits

Do not allow one user to exhaust the voice provider budget.

---

## 28. SECURITY HARD BOUNDARIES

Voice AI must NEVER:

- bypass authentication
- bypass RBAC
- treat voice as privileged authorization
- expose restricted data
- reveal secrets
- execute arbitrary SQL/PHP/shell
- call arbitrary URLs
- bypass F24
- bypass F25
- silently execute high-risk actions
- retain audio unnecessarily
- continue recording without the user's explicit action
- use speaker identity to override ERP authentication

---

# COMPLETION CRITERIA

F28 is complete when an authenticated ERP user can naturally speak to the AI, receive a transcription and spoken response, navigate the ERP, ask business questions, interact with documents and proactive intelligence, and initiate F25 tasks — while receiving exactly the same permissions, governance, safety, observability, and audit treatment as text-based AI.

The architecture should be:

**Voice → Speech-to-Text → Existing AI → F24 Governance → F25/F26/F27/etc. → Response → Text-to-Speech**

The voice layer must remain an interface, not a security boundary.