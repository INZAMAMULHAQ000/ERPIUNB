We are implementing a new top-level **AI module** in an existing ERP application.

IMPORTANT TECH STACK:

- Core PHP
- MySQL
- HTML
- CSS
- Vanilla JavaScript
- No PHP framework
- No Laravel
- No Symfony
- No Node.js backend
- No React/Vue/Angular

Before making changes, inspect the existing ERP architecture and follow its established patterns for:

- Folder/module structure
- Page routing
- Authentication
- Authorization and permissions
- Database connection
- Sidebar navigation
- Header/layout
- Reusable UI components
- AJAX/API endpoints
- Error handling

Do not introduce a framework or parallel architecture.

---

# AI F1 OBJECTIVE

Create the foundational structure for a new **AI module**.

This step is ONLY for module architecture and UI scaffolding.

Do NOT connect to any AI provider yet.

Do NOT implement OpenAI, OpenRouter, NVIDIA NIM, or any external API.

---

# 1. Add AI as a top-level ERP module

Add a new top-level sidebar/navigation module:

**AI**

The AI module navigation should support:

- AI Assistant
- Management Center
  - Providers
  - Models
  - Agents
  - Knowledge
  - Tools
- Automation
- Monitoring
- Settings

Use the existing ERP navigation/sidebar implementation.

Do not create a separate navigation system.

The AI module must visually match the existing ERP UI.

---

# 2. Create AI module folder structure

First inspect how existing modules such as Procurement are structured.

Then create the AI module using the same architectural conventions.

For example, depending on the existing project structure, organize AI-related files into logical areas such as:

- pages
- includes
- services
- api
- assets
- javascript
- CSS
- configuration

Do NOT blindly create a new architecture if the ERP already has an established module structure.

The primary rule:

**AI must look structurally native to the existing Core PHP ERP.**

---

# 3. Add AI module routes/pages

Create pages/routes equivalent to the existing ERP page navigation pattern for:

- AI Dashboard / Assistant
- AI Management Center
- Providers
- Models
- Agents
- Knowledge
- Tools
- Automation
- Monitoring
- Settings

The logical URLs should follow the existing URL style of the ERP.

If the ERP uses query-string routing, follow that.

If it uses individual PHP pages, follow that.

Do not introduce a new router.

When entering the AI module root, default to:

**AI Assistant**

---

# 4. Permission foundation

Inspect the existing ERP permission system.

Add the following logical permissions using the existing database and authorization architecture:

```text
ai.assistant.access

ai.providers.view
ai.providers.create
ai.providers.update
ai.providers.delete

ai.models.view
ai.models.manage

ai.agents.view
ai.agents.manage

ai.knowledge.view
ai.knowledge.manage

ai.tools.view
ai.tools.manage

ai.monitoring.view

ai.settings.manage
```

Important:

- Do not create a separate permission system.
- Reuse existing role/permission tables.
- Reuse existing permission checking functions.
- Ensure unauthorized users cannot directly access protected AI pages.
- Follow existing ERP behavior for denied access.

If the ERP permission system uses a different naming convention, adapt these permissions while preserving the same logical capability structure.

---

# 5. Create foundational AI PHP contracts/configuration

Since this is Core PHP, do NOT create TypeScript interfaces.

Instead, create a clean PHP-based foundation for AI domain structures.

Create reusable definitions/classes/configuration structures for:

- AI Provider
- AI Model
- AI Agent
- AI Conversation
- AI Message
- AI Tool
- AI Tool Execution
- AI Knowledge Source
- AI Execution
- AI Audit Log

Do not over-engineer.

At this stage these can be represented using:

- PHP classes
- Configuration arrays
- Constants
- Helper functions

Choose the approach that best matches the existing ERP architecture.

The goal is simply to establish consistent domain terminology for future development.

---

# 6. Create provider abstraction foundation

Prepare a provider-agnostic architecture.

Create a generic PHP provider adapter contract/base abstraction that future providers can implement.

The abstraction should conceptually support:

```text
testConnection()
listModels()
chat()
```

For example, depending on existing PHP version and coding conventions, create an interface or abstract class conceptually similar to:

```php
interface AIProviderAdapter
{
    public function testConnection();

    public function listModels();

    public function chat(array $request);
}
```

Also prepare generic data structures for:

- Connection Result
- Chat Request
- Chat Response

Important:

- Do not implement any real provider.
- Do not install SDKs.
- Do not make HTTP/API calls.
- Do not hardcode OpenAI into the core architecture.

This must support future providers through adapters.

---

# 7. Create AI Management Center

Create a functional Management Center page.

It should contain exactly these five primary management sections:

### Providers
Manage AI service providers and connections.

### Models
Manage available AI models and configurations.

### Agents
Configure specialized AI agents.

### Knowledge
Manage company knowledge and context sources.

### Tools
Manage ERP capabilities exposed to AI.

Each section should have:

- Existing ERP-compatible icon
- Title
- Short description
- Clear click/navigation action

Use the existing ERP cards, page headers, spacing, colors, and design patterns.

Do not create a radically different UI.

---

# 8. Create placeholder pages

Create clean functional placeholder pages for:

- AI Assistant
- Providers
- Models
- Agents
- Knowledge
- Tools
- Automation
- Monitoring
- Settings

These should not contain fake data.

Use professional empty states or "Coming Soon" content where appropriate.

All pages must load successfully through normal ERP navigation.

---

# 9. Create central AI module configuration

Create a central PHP configuration file for the AI module.

It should define future capabilities conceptually including:

```php
'assistant'
'providers'
'models'
'agents'
'knowledge'
'tools'
'automation'
'monitoring'
'settings'
```

Example concept:

```php
return [
    'module' => 'ai',

    'capabilities' => [
        'assistant'  => true,
        'providers'  => true,
        'models'     => true,
        'agents'     => true,
        'knowledge'  => true,
        'tools'      => true,
        'automation' => true,
        'monitoring' => true,
        'settings'   => true,
    ],
];
```

Adapt to the existing project's configuration style.

This configuration should not yet control business functionality.

It is only the central foundation for future AI features.

---

# 10. Database changes

Only make database changes if required by the existing ERP architecture.

For AI F1:

- Do NOT create provider API credential tables yet unless necessary for architectural consistency.
- Do NOT store API keys.
- Do NOT create conversation tables yet.
- Do NOT create vector/RAG tables.

Only add database records/tables necessary for:

- Module registration
- Navigation registration
- Permission registration

Follow the existing ERP database conventions.

Provide safe SQL migration/installation scripts if the project uses manual SQL migrations.

---

# IMPORTANT CONSTRAINTS

Do NOT:

- Install Laravel.
- Install Symfony.
- Introduce Composer packages unless already required by the existing project.
- Install Node.js backend dependencies.
- Connect to OpenAI.
- Connect to OpenRouter.
- Connect to NVIDIA NIM.
- Make any external API calls.
- Store API keys.
- Build the actual chat system.
- Implement agents.
- Implement autonomous execution.
- Implement RAG.
- Modify Procurement functionality.
- Refactor unrelated ERP architecture.

This step is strictly the **AI module foundation**.

---

# COMPLETION CHECKLIST

AI F1 is complete only when:

### Module Integration

- AI appears in the existing ERP navigation.
- AI follows the existing module/navigation architecture.
- AI defaults to the Assistant page.
- Existing modules remain unaffected.

### Pages

The following pages are reachable:

- AI Assistant
- Management Center
- Providers
- Models
- Agents
- Knowledge
- Tools
- Automation
- Monitoring
- Settings

### Permissions

- AI permissions are registered through the existing permission system.
- Existing authorization functions protect AI pages.
- Unauthorized users cannot access restricted pages directly.

### Architecture

- AI module folder structure follows existing project conventions.
- Central AI configuration exists.
- Foundational AI domain structures exist.
- Provider adapter abstraction exists.
- No provider implementation exists yet.

### UI

- Management Center contains exactly five navigation sections:
  - Providers
  - Models
  - Agents
  - Knowledge
  - Tools

### Safety

- No API keys are stored.
- No external API calls occur.
- No AI SDK/provider is integrated.

### Validation

Run the existing ERP validation process and ensure:

- No PHP syntax errors.
- No broken includes.
- No database errors.
- No broken navigation.
- Existing Procurement pages still work.

After implementation, provide a concise implementation report containing:

1. Files created.
2. Files modified.
3. Database changes.
4. Permission changes.
5. Architectural decisions made based on the existing Core PHP ERP structure.
6. Any known limitations.
7. Exact manual testing steps.