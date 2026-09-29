Implement **AI F11 — AI Knowledge Base & RAG Foundation** in the existing Core PHP + MySQL ERP.

### Objective

Allow users to give the ERP's AI its own trusted knowledge sources.

The AI should be able to answer questions using uploaded and managed organizational knowledge such as:

- Company policies
- SOPs
- Procurement procedures
- Employee handbooks
- Internal guides
- Product documentation
- Training material
- Department knowledge

This phase introduces a controlled **Knowledge Base + Retrieval-Augmented Generation (RAG)** architecture.

The AI must distinguish between:

1. General model knowledge
2. ERP live data retrieved through AI Tools
3. Internal organizational knowledge retrieved through Knowledge Base

Do not mix these sources invisibly.

---

# 1. Create Knowledge Base Architecture

Create a reusable architecture:

```text
Knowledge Source
      ↓
Upload / Register
      ↓
Text Extraction
      ↓
Chunking
      ↓
Embedding
      ↓
Vector Storage
      ↓
Retrieval
      ↓
Permission Filtering
      ↓
Relevant Context
      ↓
AI Response
```

Use Core PHP + MySQL architecture.

Do not introduce a PHP framework.

---

# 2. Knowledge Base Entities

Create database structures consistent with existing conventions.

## Knowledge Collections

Logical fields:

- id
- company_id / tenant scope
- name
- description
- module
- visibility
- status
- created_by
- created_at
- updated_at

Examples:

```text
Company Policies
Procurement SOPs
HR Handbook
Finance Procedures
```

---

## Knowledge Sources

Logical fields:

- id
- collection_id
- company_id / tenant scope
- title
- source_type
- original_filename
- storage_path
- mime_type
- file_size
- checksum
- status
- extraction_status
- chunk_count
- created_by
- created_at
- updated_at

Possible source types for architecture:

```text
file
text
url
database
```

For AI F11, implement only:

```text
file
text
```

Do not implement arbitrary web URL crawling yet.

---

## Knowledge Chunks

Logical fields:

- id
- source_id
- collection_id
- company_id / tenant scope
- chunk_index
- content
- content_hash
- metadata_json
- embedding_reference
- created_at

Do not expose unrestricted chunk content to unauthorized users.

---

## Knowledge Retrieval Logs

Logical fields:

- id
- user_id
- conversation_id
- collection_id
- source_ids_json
- query_hash/query_summary
- chunks_used
- created_at

Do not unnecessarily store full sensitive user prompts.

---

# 3. File Upload Support

Implement secure knowledge source uploads.

Initially support only practical document types that can be reliably extracted in the existing server environment.

Recommended initial support:

- TXT
- PDF
- DOCX
- MD

Only enable formats for which safe extraction is actually available.

Validate:

- File extension
- MIME type
- File size
- Upload errors
- Storage path
- Access permissions

Do not trust extension alone.

Store uploaded files outside publicly executable web directories where possible.

Never execute uploaded files.

---

# 4. Text Source Support

Allow authorized users to manually create a knowledge source from text.

Example:

```text
Title: Procurement Approval Policy

Content:
All purchase requisitions above AED ...
```

This should enter the same pipeline:

```text
Text
↓
Normalize
↓
Chunk
↓
Embed
↓
Index
```

---

# 5. Text Extraction Layer

Create a reusable text extraction service.

Architecture should support extractors by source type.

Conceptual interface:

```text
supports($source)
extract($source)
normalize($text)
```

Requirements:

- Remove unsupported/binary data.
- Normalize encoding.
- Preserve meaningful headings where possible.
- Apply size limits.
- Fail safely.
- Store extraction errors without exposing server internals.

Do not use AI to execute or interpret files during extraction.

---

# 6. Document Chunking

Create a reusable chunking service.

Requirements:

- Configurable chunk size.
- Configurable overlap.
- Prefer semantic boundaries where practical:
  - headings
  - paragraphs
  - sentences
- Preserve chunk order.
- Attach metadata such as:
  - source title
  - chunk index
  - section/page if available

Avoid splitting important context unnecessarily.

Do not create one giant chunk per document.

---

# 7. Embedding Provider Architecture

Create a generic embedding provider abstraction.

It should support future providers such as:

- OpenAI-compatible embedding APIs
- OpenRouter-compatible services where applicable
- NVIDIA NIM
- Local embedding services

Do not hardcode one provider into Knowledge Base logic.

Conceptual methods:

```text
embed(texts)
getDimensions()
isAvailable()
```

Provider credentials must use the existing AI provider/configuration security architecture.

Do not expose embedding API keys to frontend.

---

# 8. Embedding Model Management

Integrate embedding models into the existing AI Management Center.

Extend model/provider capabilities to distinguish:

```text
chat
embedding
```

A model may support one or both depending on provider configuration.

Allow administrators to configure:

- Embedding provider
- Embedding model
- Dimensions if required
- Enabled status

Do not assume every chat provider supports embeddings.

---

# 9. Vector Storage Abstraction

Create a vector storage abstraction.

The Knowledge Base must not directly depend on a single vector database implementation.

Conceptual interface:

```text
store(embeddings, metadata)
search(queryEmbedding, filters)
deleteBySource(sourceId)
```

For the initial implementation:

Use the simplest reliable option compatible with the current Core PHP + MySQL environment.

Possible initial approaches:

- MySQL storage with application-level similarity calculation for small datasets
- MySQL vector capability only if actually supported by deployed version
- External vector provider adapter architecture for future expansion

Do not require Redis, Elasticsearch, or a new framework.

Clearly document the initial scalability limitation.

---

# 10. Initial Similarity Search

Implement semantic retrieval:

```text
User Question
      ↓
Create Query Embedding
      ↓
Permission/Tenant Filters
      ↓
Similarity Search
      ↓
Top Relevant Chunks
```

Requirements:

- Configurable top-K.
- Minimum relevance threshold.
- Maximum context size.
- Tenant/company filtering before results are exposed.
- Collection visibility filtering.
- Deterministic result limits.

Do not dump the entire knowledge base into the prompt.

---

# 11. Knowledge Permissions & Visibility

Knowledge Base access must respect:

- Company/tenant scope
- User permissions
- Collection visibility
- Module access where relevant

Create sensible visibility options consistent with existing ERP architecture, for example:

```text
private
company
restricted
```

For restricted collections, support explicit permission requirements or authorized roles/users.

The exact implementation should fit existing RBAC architecture.

Critical rule:

A user must never retrieve restricted knowledge through AI if they cannot access it through the Knowledge Base UI.

---

# 12. Knowledge Retrieval Tool

Integrate Knowledge Base through the AI Tool Framework from AI F8.

Create a controlled read-only tool conceptually like:

```text
knowledge.search
```

Input:

- query
- optional collection scope

Output:

- Relevant chunk excerpts
- Source metadata
- Relevance information where useful

The AI must not directly query vector storage.

All retrieval goes through the registered tool.

Apply:

- Permission checks
- Tenant filtering
- Result limits
- Context size limits

---

# 13. Automatic Knowledge Retrieval

For AI Assistant conversations, allow controlled automatic retrieval.

Recommended flow:

```text
User Question
      ↓
AI decides knowledge lookup is useful
      ↓
knowledge.search
      ↓
Relevant chunks
      ↓
AI answers with grounded context
```

Do not force retrieval for every message.

Do not allow unlimited repeated retrieval loops.

Respect AI F8 tool-call limits.

---

# 14. Source Attribution

When AI uses Knowledge Base content, show clear source attribution.

Example:

```text
According to the Procurement SOP:

Purchase requisitions above AED 10,000 require...

Sources:
• Procurement SOP 2026
• Section: Approval Limits
```

Do not fabricate citations.

Attribution must originate from retrieved chunk metadata.

Keep citations understandable for non-technical users.

---

# 15. Knowledge vs ERP Data vs General AI

Create clear internal source separation.

The AI system instruction should conceptually distinguish:

```text
[GENERAL KNOWLEDGE]
Model knowledge

[ERP DATA]
Information returned by approved ERP tools

[COMPANY KNOWLEDGE]
Information retrieved from authorized knowledge sources
```

Rules:

- Never claim general model knowledge came from company policy.
- Never claim ERP live data came from a document.
- Never invent source citations.
- If retrieved knowledge is insufficient, say so.

---

# 16. Knowledge Base UI

Implement:

**AI → Knowledge**

Features:

### Collections

- List collections
- Create collection
- Edit collection
- Archive/deactivate collection

### Sources

- Upload document
- Add text source
- View processing status
- View chunk count
- Reprocess source
- Remove source

### Statuses

Example:

```text
Uploaded
Extracting
Chunking
Embedding
Indexed
Failed
```

Do not expose raw embeddings in the UI.

---

# 17. Processing Pipeline

Implement controlled source processing:

```text
Upload
↓
Validate
↓
Store
↓
Extract Text
↓
Normalize
↓
Chunk
↓
Generate Embeddings
↓
Store Vectors
↓
Mark Indexed
```

For AI F11, synchronous processing is acceptable for small documents if background jobs do not yet exist.

However:

- Use processing limits.
- Avoid PHP timeouts for large files.
- Fail gracefully.
- Design the service so future background processing can reuse the same pipeline.

Do not block normal ERP requests indefinitely.

---

# 18. Reprocessing

Allow authorized users to reprocess a source.

Before reprocessing:

- Mark old chunks/vectors appropriately.
- Prevent duplicate active indexes.
- Replace outdated chunks safely.
- Preserve audit history where practical.

Use transactions where applicable.

---

# 19. Duplicate Detection

Use checksum/content hashing to detect duplicate uploads where practical.

Warn users when:

- Same file already exists in the same collection.
- Same content is being indexed again.

Do not silently create unnecessary duplicate vectors.

---

# 20. Source Deletion

When a source is removed:

- Remove/disable associated chunks.
- Remove vectors through vector abstraction.
- Prevent future retrieval.
- Preserve minimal audit record if required.
- Do not leave orphaned searchable embeddings.

Deletion must respect tenant/company boundaries.

---

# 21. Knowledge Processing Limits

Add configurable limits:

- Maximum upload size
- Maximum extracted text size
- Maximum chunks per source
- Maximum embedding batch size
- Maximum retrieval results
- Maximum context characters/tokens

Fail safely if limits are exceeded.

Do not allow a single upload to exhaust system resources.

---

# 22. Security Requirements

Mandatory:

- File type validation
- MIME validation
- Size limits
- Safe storage
- No execution of uploaded files
- Tenant isolation
- Permission filtering
- Restricted collection protection
- API keys server-side only
- No raw embedding exposure
- Retrieval result limits
- XSS-safe source titles/content rendering
- No arbitrary URL crawling
- No unrestricted filesystem access

---

# 23. Audit Logging

Log relevant events:

- Collection created/updated
- Source uploaded
- Source processing started
- Source indexed
- Source failed
- Source reprocessed
- Source removed
- Knowledge retrieval performed

Do not log full sensitive document contents unnecessarily.

---

# Important Constraints

Do NOT implement yet:

- Web crawling
- Automatic internet research
- Arbitrary URL ingestion
- Background worker infrastructure
- Autonomous source ingestion
- Unrestricted file types
- External vector database dependency as mandatory
- Knowledge-based write actions

This step is:

**Secure Internal Knowledge Base + RAG Foundation**

---

# Completion Criteria

AI F11 is complete when:

- Knowledge collections exist.
- Users can upload supported documents.
- Users can add text knowledge.
- Documents are extracted and chunked.
- Embeddings are generated through configurable providers.
- Vectors are stored through an abstraction.
- Semantic retrieval works.
- Permissions and tenant isolation are enforced.
- AI can retrieve relevant company knowledge.
- AI responses show real source attribution.
- Sources can be reprocessed and removed safely.
- No unauthorized knowledge is exposed.

After implementation, provide:

- Files created
- Files modified
- Database changes
- Supported source types
- Extraction approach
- Chunking strategy
- Embedding architecture
- Vector storage approach
- Permission model
- Security controls
- Known scalability limitations