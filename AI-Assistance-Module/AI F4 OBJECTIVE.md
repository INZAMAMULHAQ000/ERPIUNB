Implement **AI F4 — Provider Connectivity & Model Discovery** in the existing Core PHP + MySQL ERP.

### Objective

Make configured AI providers actually usable by implementing:

1. Secure provider connection testing
2. Provider adapter architecture
3. Automatic model discovery where supported
4. Manual fallback where discovery is unavailable

This is the first step that may make external AI API calls.

Do NOT build the AI Assistant/chat interface yet.

Do NOT implement agents or autonomous execution.

---

# 1. Provider Adapter Architecture

Implement the provider abstraction designed in AI F1.

Create a reusable Core PHP provider adapter structure.

Each provider adapter must implement a consistent contract for:

- `testConnection()`
- `listModels()`
- `getProviderInfo()` if useful

Do not place provider-specific HTTP logic directly inside page controllers.

Create a provider factory/resolver that selects the correct adapter based on `provider_type`.

The architecture must allow future providers to be added without modifying unrelated AI core logic.

---

# 2. Implement Provider Adapters

Implement adapters for:

### OpenAI

Provider type:

```text id="xygdo6"
openai
```

### OpenRouter

Provider type:

```text id="ih3k2v"
openrouter
```

### NVIDIA NIM

Provider type:

```text id="6p3xge"
nvidia_nim
```

### OpenAI Compatible

Provider type:

```text id="fdf2dm"
openai_compatible
```

### Custom

Provider type:

```text id="h3v4gi"
custom
```

Important:

- Use the provider's configured base URL where appropriate.
- Never hardcode API keys.
- Retrieve credentials only server-side through the secure credential helper created in AI F2.
- Never expose decrypted credentials to the browser.
- Keep HTTP communication isolated inside provider adapters.

For providers that use OpenAI-compatible APIs, reuse common HTTP logic where appropriate without creating unnecessary duplication.

---

# 3. HTTP Client

Use the existing ERP HTTP communication mechanism if one exists.

If none exists, create a small reusable Core PHP HTTP client/helper using cURL.

Requirements:

- Configurable timeout
- Connection timeout
- JSON request support
- JSON response parsing
- HTTP status handling
- Safe error handling
- Request headers
- No credential logging

Do not introduce a framework.

Do not expose raw provider error responses directly to end users if they may contain sensitive information.

---

# 4. Test Provider Connection

Add a **Test Connection** action to the Providers management page.

Only users with:

```text id="x4gh2e"
ai.providers.update
```

may test connections.

Connection test behavior:

1. User clicks Test Connection.
2. Frontend sends provider ID to a protected backend endpoint.
3. Backend validates permission.
4. Backend retrieves provider configuration.
5. Backend securely decrypts API key server-side.
6. Correct provider adapter is resolved.
7. Adapter performs a lightweight authentication/availability request.
8. Backend returns a sanitized result.

Possible result:

### Success

Display:

- Connected successfully
- Provider name
- Optional account/API status information if safely available

### Failure

Display a safe message such as:

- Authentication failed
- Connection timeout
- Invalid endpoint
- Provider unavailable
- Connection failed

Never display:

- API key
- Authorization header
- Full sensitive request headers
- Decrypted credentials

---

# 5. Connection Status Tracking

Extend the provider database structure if needed.

Add logical fields such as:

- last_connection_status
- last_connection_at
- last_connection_message

Suggested statuses:

```text id="vvxsvm"
never_tested
success
failed
```

Requirements:

- Store sanitized messages only.
- Never store raw API keys or request headers.
- Update status after each test.
- Display connection status in the Providers list.

---

# 6. Automatic Model Discovery

Add a **Discover Models** action for active providers.

The feature should:

1. Require appropriate permission.
2. Require the provider to be active.
3. Attempt connection validation if needed.
4. Resolve the provider adapter.
5. Call `listModels()`.
6. Receive provider model metadata.
7. Normalize results into the ERP's generic AI model structure.
8. Present discovered models to the user before saving.

Do NOT automatically enable or set every discovered model as default.

---

# 7. Model Discovery Review Screen

When models are discovered, show a review interface.

Display:

- Provider model ID
- Suggested display name
- Detected capabilities where available
- Selection checkbox
- Existing/new status

Requirements:

- Existing models must be identified.
- Avoid duplicates.
- User chooses which models to import.
- User explicitly confirms import.

For metadata unavailable from the provider:

- Leave fields null/default.
- Do not invent token limits or capabilities.

---

# 8. Import Discovered Models

After confirmation:

- Create selected new model records.
- Skip duplicates safely.
- Do not overwrite manually configured models without explicit future functionality.
- Set imported models inactive by default unless existing ERP UX strongly suggests otherwise.
- Never automatically assign default model.

Use transactions where appropriate.

Log:

- Discovery attempted
- Discovery succeeded/failed
- Number of models discovered
- Number imported

Do not log credentials.

---

# 9. Manual Provider Compatibility

For providers where model discovery is unsupported or unreliable:

Display a clear message:

```text id="mvzxkr"
Automatic model discovery is not available for this provider.
You can add models manually.
```

The existing AI F3 manual model creation flow must remain functional.

Do not make discovery mandatory.

---

# 10. Provider-Specific Base URLs

Centralize default endpoint configuration.

Do not scatter URLs throughout page logic.

Support:

- Default provider endpoint
- Custom base URL override where allowed

For OpenAI Compatible and Custom providers:

- Base URL handling must be configurable.
- Validate URL format server-side.
- Never allow arbitrary dangerous internal URL access without safeguards.

Apply SSRF protections appropriate to the deployment environment.

At minimum:

- Validate scheme.
- Prefer HTTPS.
- Reject malformed URLs.
- Consider blocking localhost, loopback, link-local, and private/internal network targets unless explicitly required and securely configured by system administrators.

---

# 11. Security Requirements

Mandatory:

- API keys are decrypted only when required server-side.
- API keys never reach JavaScript/browser.
- API keys never appear in responses.
- API keys never appear in logs.
- All provider actions require server-side permission validation.
- Use CSRF protection according to existing ERP architecture.
- Validate provider ID.
- Use prepared statements.
- Sanitize provider error messages.
- Apply request timeouts.
- Prevent unrestricted SSRF through custom endpoints.
- Do not disable TLS certificate verification.

---

# 12. Audit Logging

Log relevant events through the existing ERP audit system:

- Provider connection test succeeded
- Provider connection test failed
- Model discovery started
- Model discovery succeeded
- Model discovery failed
- Models imported

Do not include secrets or sensitive request data.

---

# Important Constraints

Do NOT:

- Build the AI chat UI.
- Build conversations.
- Build AI agents.
- Implement tool execution.
- Implement autonomous tasks.
- Implement RAG.
- Add provider API keys to frontend JavaScript.
- Automatically import all discovered models.
- Automatically set imported models as default.

This step is only:

**Provider Connectivity + Model Discovery**

---

# Completion Criteria

AI F4 is complete when:

- Providers can be tested securely.
- Correct adapter is selected based on provider type.
- Connection results are stored safely.
- Connection status appears in provider management.
- Supported providers can discover models.
- Discovered models can be reviewed before import.
- Selected models can be imported.
- Duplicate models are handled safely.
- Manual model creation remains available.
- Credentials never reach frontend.
- No chat/assistant functionality exists yet.

After implementation, provide:

- Files created
- Files modified
- Database changes
- Provider adapters implemented
- Security measures implemented
- Known limitations