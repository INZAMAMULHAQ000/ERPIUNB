Implement **AI F2 — AI Provider Management** in the existing Core PHP + MySQL ERP.

## Existing stack

- Core PHP
- MySQL
- HTML
- CSS
- Vanilla JavaScript
- No PHP framework

Follow all existing ERP conventions established in AI F1 and the existing application.

Do not introduce a framework or unrelated architecture.

---

# OBJECTIVE

Build a functional **AI Provider Management** system at:

AI → Management Center → Providers

Administrators must be able to:

- View configured AI providers
- Add a provider
- Edit a provider
- Enable/disable a provider
- Set one provider as default
- Delete a provider

This step manages provider configuration only.

IMPORTANT:

**Do NOT make real API calls yet.**

Connection testing and actual provider communication will be implemented in a later step.

---

# 1. Create AI providers database table

Create a database table following existing ERP naming conventions.

Suggested logical structure:

### ai_providers

Fields should include:

- id
- name
- slug or unique identifier
- provider_type
- base_url (nullable)
- api_key_encrypted (nullable)
- organization_id or tenant/company scope if the ERP architecture requires it
- is_active
- is_default
- configuration_json (nullable)
- created_at
- updated_at
- created_by
- updated_by

Adapt field names and types to existing database conventions.

## Important security requirement

The API key must NOT be stored as plain text.

Create a reusable secure credential handling foundation.

Use the existing application encryption mechanism if one exists.

If none exists:

- Create a dedicated reusable encryption helper for sensitive AI credentials.
- Keep the encryption key outside database records.
- Use environment/server configuration where compatible with the existing Core PHP architecture.
- Never expose the decrypted API key in HTML, JavaScript, AJAX responses, or provider listing APIs.

Do not display stored API keys after saving.

At most, display a masked status such as:

`Configured ••••1234`

if the last few characters can be safely derived without exposing the full secret.

If secure encryption cannot be implemented safely within the current project configuration, stop and clearly report the limitation rather than silently storing plaintext credentials.

---

# 2. Provider types

Create a centralized provider type registry.

Initially support these provider types:

### OpenAI

Identifier:

`openai`

### OpenRouter

Identifier:

`openrouter`

### NVIDIA NIM

Identifier:

`nvidia_nim`

### OpenAI Compatible

Identifier:

`openai_compatible`

### Custom

Identifier:

`custom`

The registry should define metadata such as:

- identifier
- display name
- description
- whether custom base URL is supported
- whether API key is expected

Do not hardcode provider-specific logic throughout UI pages.

Keep provider metadata centralized.

Do not yet implement provider adapters.

---

# 3. Providers list page

Replace the existing Providers placeholder page with a functional management page.

Display configured providers in a professional ERP-compatible table or card layout.

Recommended columns:

- Provider Name
- Provider Type
- Base URL
- Credential Status
- Status
- Default
- Last Updated
- Actions

Do not expose API keys.

Actions:

- Edit
- Enable / Disable
- Set as Default
- Delete

Add:

- Search
- Empty state
- Add Provider button

Use existing ERP UI components and patterns.

---

# 4. Add Provider

Create an Add Provider form.

Fields:

### Provider Name

Required.

Example:

`Company OpenAI`

### Provider Type

Required dropdown using the centralized provider registry.

### Base URL

Optional/conditional.

Show or require based on provider type metadata.

### API Key

Optional at database level but normally expected for API-based providers.

Requirements:

- Password-type input.
- Never redisplay the value after save.
- Encrypt before database storage.
- Never return decrypted value to browser.

### Additional Configuration

Provide a structured JSON/configuration area only if consistent with existing ERP UI patterns.

Keep it optional.

Do not add provider-specific configuration fields yet unless necessary for architecture.

### Status

- Active
- Inactive

Save using the existing ERP form/AJAX pattern.

Perform:

- Server-side validation
- CSRF protection if the ERP uses it
- Permission validation
- Duplicate validation where appropriate

---

# 5. Edit Provider

Allow editing:

- Name
- Provider Type
- Base URL
- API Key (optional replacement)
- Additional configuration
- Active/inactive status

Critical API key behavior:

When editing an existing provider:

- Never populate the API key field with the stored key.
- Show a message such as `API key already configured`.
- Leave the password field blank.
- Only replace the stored credential when a new value is explicitly entered.
- Do not accidentally erase the existing credential when the edit form is saved with an empty API key field.

---

# 6. Provider activation

Implement enable/disable functionality.

Rules:

- Inactive providers cannot be selected for future AI operations.
- A provider can be activated again.
- Changing status requires appropriate update permission.
- Prevent inconsistent default provider state.

If a provider is disabled while it is the default provider:

Choose a safe behavior consistent with the existing ERP UX, such as:

- Require another active provider to be selected as default first, OR
- Automatically clear default status after confirmation.

Do not leave a disabled provider marked as default.

---

# 7. Default provider

Allow one provider to be marked as default.

Rules:

- Only one default provider may exist within the applicable ERP scope.
- Default provider must be active.
- Setting a new default must atomically remove default status from the previous provider.
- Use a database transaction where appropriate.
- Enforce the rule server-side, not only in JavaScript.

Consider company/tenant scope if the ERP is multi-company.

---

# 8. Delete provider

Allow provider deletion.

Before deletion:

- Require confirmation.
- Check whether the provider is referenced by future AI configuration records if such references already exist.
- If no references exist yet, allow deletion.
- Use existing ERP delete confirmation patterns.

Deletion requires:

`ai.providers.delete`

Never expose the API key during deletion operations.

---

# 9. Permission integration

Use the permissions created in AI F1.

Enforce:

```text
ai.providers.view
ai.providers.create
ai.providers.update
ai.providers.delete
```

Expected behavior:

### View

Can access provider list.

### Create

Can add providers.

### Update

Can edit, activate/deactivate, and set default.

### Delete

Can delete providers.

Do not rely solely on hiding buttons.

Every server-side action must independently validate permissions.

---

# 10. Create provider service/repository layer

Do not place all SQL directly inside page templates if the existing ERP architecture allows separation.

Create reusable provider management functions/services for operations such as:

- getProviders()
- getProviderById()
- createProvider()
- updateProvider()
- deleteProvider()
- setDefaultProvider()
- toggleProviderStatus()

Also create secure credential helper functions.

Follow the existing Core PHP coding style.

Use prepared statements for all database operations.

Do not create a framework-style ORM.

---

# 11. Audit logging

Integrate provider management actions with the existing ERP audit/activity logging system if available.

Log events such as:

- Provider created
- Provider updated
- Provider activated
- Provider deactivated
- Default provider changed
- Provider deleted

Never include:

- API keys
- decrypted credentials
- full sensitive configuration

If the existing audit system does not support this yet, create a minimal AI audit hook compatible with future AI F-series development, but do not create unnecessary duplicate logging infrastructure.

---

# DATABASE INTEGRITY REQUIREMENTS

Ensure:

- Provider identifier uniqueness is enforced appropriately.
- Only one default provider exists per applicable scope.
- Disabled providers cannot remain default.
- Timestamps follow existing ERP conventions.
- Foreign keys/indexes are used if consistent with the current database architecture.
- Sensitive credentials are never stored in plaintext.

---

# IMPORTANT: DO NOT IMPLEMENT YET

Do NOT:

- Make OpenAI API calls.
- Make OpenRouter API calls.
- Make NVIDIA NIM API calls.
- Test provider connections.
- Fetch models.
- Build AI chat.
- Build agents.
- Build RAG.
- Add background workers.
- Add autonomous execution.

This is strictly **Provider Configuration Management**.

---

# SECURITY REQUIREMENTS

This section is mandatory.

- Use prepared SQL statements.
- Validate all input server-side.
- Enforce permissions server-side.
- Use existing CSRF protection.
- Encrypt API keys before database storage.
- Never send decrypted keys to frontend.
- Never log credentials.
- Never expose credentials in error messages.
- Escape output correctly to prevent XSS.

---

# ACCEPTANCE CRITERIA

AI F2 is complete when:

### Provider Management

- Providers list page works.
- Admin can create provider.
- Admin can edit provider.
- Admin can enable/disable provider.
- Admin can set one active provider as default.
- Admin can delete provider.

### Security

- API keys are encrypted at rest.
- API keys never appear in provider listings.
- API keys never populate edit forms.
- API keys are never returned by AJAX endpoints.
- API keys are never written to logs.

### Data Integrity

- Only one default provider exists.
- Default provider must be active.
- Database operations use prepared statements.
- Duplicate identifiers are handled.

### Permissions

- View/create/update/delete permissions are independently enforced.
- Direct requests cannot bypass permissions.

### Regression

- Existing ERP modules continue working.
- No actual external AI API calls occur.

After implementation, provide:

1. Files created.
2. Files modified.
3. Database migration/SQL changes.
4. Security approach used for API credential encryption.
5. Permission checks implemented.
6. Manual testing instructions.
7. Known limitations.

Do not proceed to model management or API connectivity.