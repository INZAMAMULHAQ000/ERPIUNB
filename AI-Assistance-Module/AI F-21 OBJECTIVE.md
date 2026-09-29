Implement **AI F21 — AI Voice Interface** in the existing **Core PHP + MySQL ERP**.

### Objective

Add a controlled voice interface so authorized ERP users can speak naturally to the AI assistant instead of typing.

Examples:

> “Show me overdue purchase requisitions.”

> “Create a task to review supplier quotations tomorrow.”

> “Summarize today's procurement activity.”

> “Open the pending requisitions page.”

> “Explain why this purchase order is delayed.”

The Voice Interface must act as another input/output layer for the existing AI architecture.

It must **not create a separate AI system**.

All voice requests must continue through:

- AI Command Center
- Intent routing
- Permission checks
- Tool/action registry
- Confirmation policies
- Audit logging
- AI Management Center
- Existing provider abstraction

---

# 1. Voice Interface Entry Points

Add voice access to:

```text
AI Command Center
AI Assistant Chat
Relevant ERP pages where appropriate
```

Initial UI controls:

```text
Microphone Button
Start Recording
Stop Recording
Cancel
Transcription Preview
Send
```

Do not automatically execute speech immediately after recording.

Default flow:

```text
Speak
↓
Speech-to-Text
↓
Transcription Preview
↓
User Send
↓
Normal AI Request Pipeline
```

This ensures users can verify recognition before execution.

---

# 2. Voice Architecture

Create a provider abstraction:

```text
Voice Input
↓
Audio Capture
↓
Audio Validation
↓
Speech Provider
↓
Transcription
↓
AI Command Pipeline
```

For responses:

```text
AI Response
↓
Optional Speech Output
↓
Text-to-Speech Provider
↓
Audio Playback
```

Voice is an interface layer only.

Do not bypass existing AI safety architecture.

---

# 3. Audio Capture

Use browser-native audio APIs where supported.

Requirements:

- Explicit microphone permission
- Start/stop recording
- Maximum recording duration
- Audio size limit
- Cancel recording
- Upload only after explicit user action

Do not continuously record users.

Do not implement background microphone monitoring.

---

# 4. Audio Validation

Before processing:

Validate:

- File exists
- Allowed MIME type
- File size
- Duration
- Company/user context
- Request authorization

Reject unsupported or oversized audio safely.

Do not trust client MIME metadata alone where server validation is possible.

---

# 5. Speech-to-Text Provider Abstraction

Create interface:

```text
SpeechToTextProviderInterface
```

Example capabilities:

```text
transcribe()
detectLanguage()
supportsAudioFormat()
```

Provider selection should reuse AI provider management architecture.

Potential future providers:

- OpenAI
- NVIDIA
- Other approved providers

Do not hardwire UI/business logic to one provider.

---

# 6. Transcription Record

Store controlled metadata:

- ID
- User
- Company
- Provider
- Model
- Language
- Duration
- Status
- Created at

Store transcript according to AI retention policy.

Do not store raw audio indefinitely by default.

Audio retention and transcript retention must be independently configurable.

---

# 7. Transcription Preview

After transcription show editable text.

Example:

```text
You said:

Show me all purchase requisitions pending for more than seven days.
```

User can:

- Edit
- Send
- Cancel
- Retry recording

Only after Send should the normal AI command pipeline process the request.

---

# 8. Language Detection

Support provider-assisted language detection.

Store detected language as metadata.

The AI should receive the transcription language where useful.

Do not assume English-only architecture.

Initial UI should be localization-ready.

---

# 9. Voice Command Processing

Once user clicks Send:

```text
Transcription
↓
Intent Detection
↓
AI Command Router
↓
Tool / Knowledge / Action / Navigation
↓
Normal Response
```

Voice commands must have exactly the same permission boundaries as typed commands.

Example:

> Delete this requisition.

Must still result in:

```text
Intent
↓
Permission Check
↓
Action Proposal
↓
Confirmation
↓
Execution
```

Voice is never authorization.

---

# 10. High-Risk Action Confirmation

For actions classified as:

```text
high
restricted
```

Require explicit visual confirmation.

Do not allow voice alone to confirm high-risk actions in this phase.

Example:

> “Approve the payment.”

System:

```text
I prepared the approval action.
Please review and confirm on screen.
```

---

# 11. Text-to-Speech

Add optional AI response playback.

User can click:

```text
Listen
```

Do not autoplay responses by default.

Architecture:

```text
Text
↓
TTS Provider Resolver
↓
Speech Generation
↓
Audio Stream/File
↓
User Playback
```

Allow future providers through abstraction.

---

# 12. TTS Controls

Provide:

- Play
- Pause
- Stop
- Replay

Optional future-ready preferences:

- Voice
- Speed
- Language

Do not store generated audio longer than necessary.

---

# 13. Browser Native TTS Fallback

Where appropriate, support browser-native speech synthesis as a fallback.

Preference hierarchy:

```text
Configured TTS Provider
↓
Browser Speech Synthesis
↓
Text Only
```

Fallback must fail safely.

---

# 14. Voice Conversation Mode

Allow optional:

```text
Voice Conversation Mode
```

Flow:

```text
User speaks
↓
Transcription preview
↓
User sends
↓
AI responds
↓
Optional playback
```

Do NOT implement continuous autonomous listening in this phase.

Each turn requires explicit user interaction.

---

# 15. Voice Context

Voice requests should reuse current conversation context.

Example:

Conversation:

> Show pending requisitions.

Then voice:

> Only those older than 10 days.

AI should use normal conversation context rules.

Do not create unrestricted audio memory.

Audio itself is not automatically used as long-term memory.

---

# 16. ERP Page Context

When activated from an ERP page, provide controlled page context.

Example:

```text
Current Module:
Procurement

Current Page:
Purchase Requisition

Current Record:
PR-2026-00124
```

Voice:

> Why is this delayed?

AI may use authorized current record context.

Do not send entire page DOM or hidden browser data to providers.

Only structured approved context.

---

# 17. Voice Navigation

Allow navigation intents.

Examples:

> Open supplier list.

> Take me to overdue requisitions.

> Go to AI Workspaces.

Use registered navigation registry from existing Command Center architecture.

Return:

- Navigation response
- Internal link
- Optional user click

Do not allow arbitrary URLs.

---

# 18. Voice Dictation Mode

Optionally support dictation into supported ERP fields.

Examples:

```text
Task Description
Internal Notes
Comments
```

Flow:

```text
Voice
↓
Transcription
↓
Insert Preview
↓
User Accept
↓
Field Updated
```

Do not directly submit forms from dictation.

Do not enable dictation for sensitive fields without explicit policy.

---

# 19. Voice Shortcuts

Allow controlled voice shortcuts.

Examples:

```text
New Task
Open AI
Search Procurement
Help Me
```

Shortcuts must map to registered internal commands.

No arbitrary shell/system commands.

---

# 20. Provider Configuration

Extend AI Management Center.

Add:

```text
Speech-to-Text Providers
Text-to-Speech Providers
Voice Usage
Voice Limits
Voice Retention
```

Provider configuration must reuse existing:

- Credential storage
- Encryption
- Health checks
- Rate limits
- Cost tracking
- Kill switches

---

# 21. Voice Usage Limits

Integrate with F16.

Support:

- Maximum audio duration
- Maximum file size
- Requests per minute
- Requests per user/day
- Monthly transcription budget
- Monthly TTS budget

When exceeded:

- Reject safely
- Explain limit
- Allow normal typed AI use

---

# 22. Cost Tracking

Track:

- Audio duration
- STT requests
- TTS requests
- Provider
- Model
- Estimated cost

Do not expose provider credentials.

Aggregate usage by:

- Company
- User where policy allows
- Provider
- Feature

---

# 23. Voice Audit Logging

Audit:

```text
Recording Uploaded
Transcription Completed
Transcription Failed
Voice Command Sent
TTS Generated
Voice Action Proposed
Voice Navigation Requested
```

Do not log raw audio into normal audit logs.

Transcript logging must respect retention/sensitivity policy.

---

# 24. Voice Retention

Separate retention controls:

```text
Raw Audio
Transcript
Generated TTS Audio
Voice Metadata
```

Suggested policy:

- Raw audio: temporary/minimal
- Transcript: follow conversation policy
- TTS audio: temporary/cache
- Metadata: operational retention

All policies must be configurable in F16 Management.

---

# 25. Sensitive Voice Data

Before sending audio externally check:

```text
Company Policy
User Authorization
Provider Policy
Voice Feature Enabled
Data Sensitivity
```

For restricted contexts:

- Block external transcription, or
- Require approved provider.

Do not silently transmit restricted audio.

---

# 26. Error Handling

Examples:

```text
Could not understand audio.
Try speaking again.

Audio too long.
Maximum duration is 2 minutes.

Speech provider unavailable.
You can type your request instead.
```

Do not expose:

- Provider keys
- Raw internal errors
- Stack traces

---

# 27. Offline / Provider Failure Fallback

If STT fails:

- Keep typed AI available.
- Allow retry where safe.
- Do not lose current conversation.

If TTS fails:

- Display normal text response.

Voice failure must not break ERP functionality.

---

# 28. Permissions

Add only if missing:

```text
ai.voice.use
ai.voice.transcribe
ai.voice.playback
ai.voice.dictate
ai.voice.manage
```

Permission flow:

```text
Voice Permission
+
AI Feature Permission
+
Underlying Tool/Action Permission
```

Voice permission does not grant ERP permissions.

---

# 29. Management Center Dashboard

Add:

```text
Voice Requests
Transcription Minutes
TTS Usage
Provider Health
Failure Rate
Estimated Cost
```

Administrators should see operational metrics.

Do not automatically expose user voice content.

---

# 30. Kill Switches

Add server-side controls:

```text
Disable Voice Input
Disable Speech-to-Text
Disable Text-to-Speech
Disable Voice Dictation
Disable Voice Commands
```

When disabled:

- UI should reflect unavailable state.
- Server must still reject requests.
- Typed AI remains unaffected.

---

# 31. Privacy Requirements

Mandatory:

- Explicit microphone permission
- No continuous listening
- No background recording
- No hidden audio capture
- Minimal audio retention
- Independent transcript retention
- External provider policy checks
- User-visible transcription
- Explicit send before AI execution
- No voice-only confirmation for high-risk actions

---

# 32. Security Requirements

Mandatory:

- Authentication
- Tenant isolation
- Audio validation
- Provider credential protection
- Rate limiting
- Usage limits
- Permission checks
- Action confirmation
- Sensitive data policy enforcement
- Audit logging
- Server-side kill switches

---

# Important Constraints

Do NOT implement:

- Always-listening assistant
- Background microphone monitoring
- Voice biometric authentication
- Speaker identification
- Voiceprint storage
- Voice-only authorization
- Voice-only approval for high-risk actions
- Automatic execution immediately after transcription
- Arbitrary voice-controlled URLs
- Arbitrary system/shell commands

This step is:

**A safe push-to-talk AI interface for ERP users that converts voice into controlled, reviewable AI requests.**

---

# Completion Criteria

AI F21 is complete when:

- Voice input exists.
- Audio capture is explicit.
- STT provider abstraction exists.
- Audio validation works.
- Transcription preview works.
- Voice commands use normal AI pipeline.
- High-risk actions require visual confirmation.
- TTS playback works.
- Browser fallback works where available.
- Current conversation context works.
- ERP page context works safely.
- Voice navigation works.
- Dictation works with preview.
- Usage limits work.
- Cost tracking works.
- Retention policies work.
- Sensitive data controls work.
- Permissions work.
- Kill switches work.
- F16 governance integration works.

After implementation, provide:

- Files created
- Files modified
- Database changes
- Voice architecture
- STT provider abstraction
- TTS provider abstraction
- Audio validation approach
- Transcription flow
- Command processing flow
- Page context rules
- Dictation flow
- Retention approach
- Privacy controls
- Security controls
- Known limitations