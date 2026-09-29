Implement **AI F3 — Model Management & Provider Model Registry** in the existing Core PHP + MySQL ERP.

### Objective

Build the AI Model Management system at:

**AI → Management Center → Models**

This step allows administrators to manually register, configure, enable/disable, and assign AI models to configured providers.

Do NOT fetch models from external providers yet.

Do NOT make any external API calls.

---

### 1. Database

Create the necessary MySQL table(s) for AI models following existing ERP naming conventions.

Each AI model should support at minimum:

- id
- provider_id
- model_id
- display_name
- description (nullable)
- model_type
- context_window (nullable)
- max_output_tokens (nullable)
- supports_chat
- supports_tool_calling
- supports_vision
- supports_embeddings
- supports_structured_output
- is_active
- is_default
- configuration_json (nullable)
- created_at
- updated_at
- created_by
- updated_by

Requirements:

- `provider_id` must reference the configured AI provider.
- `model_id` identifies the provider's actual model identifier.
- Prevent duplicate model IDs for the same provider.
- A model must belong to exactly one provider.
- Only active models can be default.
- Only one default model should exist within the applicable scope.
- Use indexes and foreign keys if consistent with existing ERP conventions.

Do not create model credentials.

---

### 2. Model types

Create a centralized model capability/type registry.

Support logical model types such as:

- chat
- reasoning
- embedding
- vision
- multimodal

A model may support multiple capabilities.

Do not hardcode model behavior into page templates.

Use centralized constants/configuration where consistent with the project architecture.

---

### 3. Models List Page

Replace the existing Models placeholder page with a functional management page.

Display models using the existing ERP table/list patterns.

Recommended columns:

- Display Name
- Model ID
- Provider
- Type / Capabilities
- Context Window
- Status
- Default
- Last Updated
- Actions

Actions:

- Edit
- Enable / Disable
- Set as Default
- Delete

Include:

- Search
- Provider filter
- Status filter
- Empty state
- Add Model button

Never expose provider credentials.

---

### 4. Add Model

Create a functional Add Model form.

Fields:

#### Provider

Required dropdown.

Only show configured providers.

Prefer active providers by default.

Inactive providers must not be selectable for a new active model.

#### Display Name

Required.

Example:

`GPT-5`

#### Model ID

Required.

Example:

`gpt-5`

This is the identifier that will later be sent to the provider API.

#### Description

Optional.

#### Model Type

Allow selecting one or more logical types/capabilities.

#### Context Window

Optional positive integer.

#### Maximum Output Tokens

Optional positive integer.

#### Capabilities

Provide switches/checkboxes for:

- Chat
- Tool Calling
- Vision
- Embeddings
- Structured Output

#### Status

- Active
- Inactive

#### Default Model

Optional.

Rules:

- An inactive model cannot be created as default.
- Only one default model may exist.

Perform server-side validation.

Use the existing ERP CSRF and permission protection mechanisms.

---

### 5. Edit Model

Allow editing all appropriate model metadata.

When changing the provider:

- Validate duplicate model IDs within the new provider.
- Preserve data integrity.

Allow:

- Display name changes
- Model ID changes
- Description changes
- Capability changes
- Token/context metadata changes
- Status changes
- Default selection

Prevent invalid states.

---

### 6. Default Model Logic

Implement default model management.

Rules:

- Only one active default model may exist within the applicable ERP scope.
- Setting a model as default must atomically remove default status from the previous default model.
- Use a database transaction.
- Disabled models cannot remain default.
- A model belonging to an inactive provider cannot be selected as default.

Server-side enforcement is mandatory.

Do not rely only on frontend controls.

---

### 7. Enable / Disable Model

Implement activation controls.

Rules:

- Disabled models cannot be used by future AI operations.
- A model belonging to an inactive provider cannot be activated unless the provider is activated first.
- Disabling the default model must not leave an invalid default state.
- Use existing confirmation UX patterns.

---

### 8. Delete Model

Implement model deletion.

Requirements:

- Require confirmation.
- Enforce `ai.models.manage` permission.
- Check for future references if any exist.
- If no references exist yet, allow deletion.
- Use prepared statements.
- Integrate with existing audit logging if available.

---

### 9. Permissions

Use the permissions created in AI F1:

- `ai.models.view`
- `ai.models.manage`

Server-side rules:

- View permission required to access model pages/data.
- Manage permission required for:
  - Create
  - Edit
  - Enable/disable
  - Set default
  - Delete

Do not rely only on hidden buttons.

Protect all direct requests and AJAX endpoints.

---

### 10. Service Layer

Create reusable Core PHP functions/services consistent with the existing ERP architecture.

Logical operations should include:

- getModels()
- getModelById()
- createModel()
- updateModel()
- deleteModel()
- setDefaultModel()
- toggleModelStatus()
- getActiveModelsByProvider()

Use prepared statements.

Do not introduce an ORM or framework.

---

### 11. Validation Rules

Enforce server-side validation for:

- Required provider
- Valid provider existence
- Provider active state
- Required display name
- Required model ID
- Duplicate provider + model ID
- Valid positive integers for token/context fields
- Valid capability values
- Default model rules
- Permission checks
- CSRF protection

---

### 12. Audit Logging

If the ERP has an audit system, log:

- Model created
- Model updated
- Model activated
- Model deactivated
- Default model changed
- Model deleted

Do not log unnecessary sensitive configuration.

---

### Important Constraints

Do NOT:

- Call any provider API.
- Fetch models automatically.
- Test provider connectivity.
- Implement AI chat.
- Implement provider adapters.
- Implement agents.
- Implement embeddings/RAG.
- Install AI SDKs.

Models in this step are manually configured registry records only.

---

### Completion Criteria

AI F3 is complete when:

- Models page is functional.
- Models can be created.
- Models can be edited.
- Models can be enabled/disabled.
- Models can be deleted.
- Provider relationship works.
- Search and filters work.
- Only valid active models/providers can become default.
- Only one default model exists.
- Permissions are enforced server-side.
- No external API calls occur.
- Existing ERP functionality remains unaffected.

After implementation, provide:

- Files created
- Files modified
- Database changes
- Validation/security measures
- Known limitations