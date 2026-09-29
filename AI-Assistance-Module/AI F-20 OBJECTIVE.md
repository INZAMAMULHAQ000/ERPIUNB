Implement **AI F20 — AI Document Intelligence** in the existing **Core PHP + MySQL ERP**.

### Objective

Build a controlled Document Intelligence system that allows users to upload, process, understand, search, extract, classify, and interact with business documents using AI.

The system should support documents such as:

- Purchase quotations
- Supplier documents
- Invoices
- Purchase orders
- Contracts
- Policies
- Procedures
- Reports
- Scanned documents
- Images containing text

Users should be able to ask:

> “What is in this document?”

> “Extract the supplier details.”

> “Compare these quotations.”

> “What payment terms are mentioned?”

> “Create a draft purchase requisition from this quotation.”

> “Find all contracts expiring in the next 90 days.”

The AI must remain controlled and must never treat extracted content as automatically trusted ERP data.

---

# 1. Document Intelligence Center

Create:

```text id="r7m2nq"
AI → Document Intelligence
```

Main sections:

```text id="3br9wz"
Documents
Upload
Processing Queue
Extraction Results
Document Types
Templates
Comparisons
Search
```

Reuse existing document/file infrastructure where possible.

Do not create duplicate file storage systems unnecessarily.

---

# 2. Document Registry

Create a centralized AI document metadata layer.

Each document should reference the existing ERP file where applicable.

Store metadata such as:

- ID
- Company ID
- Original file reference
- File name
- MIME type
- File size
- Source module
- Related ERP record
- Document type
- Processing status
- Extraction status
- Classification
- Created by
- Created at

Do not duplicate the binary file unnecessarily.

---

# 3. Supported Document Types

Initial logical types:

```text id="x9q9yj"
unknown
quotation
invoice
purchase_order
contract
policy
procedure
report
supplier_document
identity_document
other
```

Document type classification must remain reviewable.

AI classification is a suggestion unless deterministic rules establish it.

---

# 4. Processing Pipeline

Implement an asynchronous document processing lifecycle:

```text id="bqurxq"
Uploaded
↓
Queued
↓
Text Extraction
↓
OCR (if needed)
↓
Document Classification
↓
Structured Extraction
↓
Validation
↓
Indexing
↓
Ready
```

Statuses:

```text id="6ik9xw"
uploaded
queued
processing
needs_review
ready
failed
unsupported
```

Do not keep long-running OCR or AI processing inside a web request.

Use scheduler/job architecture compatible with Core PHP.

---

# 5. Text Extraction Layer

Create a provider abstraction.

Potential extraction methods:

```text id="j11zpy"
Native PDF Text
DOCX Text
Spreadsheet Text
OCR
External AI Vision
```

Use the best available registered method based on file type.

Architecture:

```text id="pup8ds"
Document
↓
Extractor Resolver
↓
Extraction Provider
↓
Normalized Text
↓
Processing Pipeline
```

Do not hardwire the entire system to one OCR/AI provider.

---

# 6. OCR Support

For scanned PDFs/images:

```text id="jvex70"
Image/PDF
↓
OCR Provider
↓
Extracted Text
↓
Confidence Metadata
↓
Document Processing
```

OCR output must be treated as potentially inaccurate.

Store confidence where available.

Do not silently convert uncertain OCR text into ERP facts.

---

# 7. Document Classification

Use controlled classification.

Possible result:

```json id="wzkbj7"
{
  "document_type": "quotation",
  "confidence": 0.94,
  "reason_summary": "Contains supplier quotation details"
}
```

Classification output must be schema-validated.

For low confidence:

```text id="7tqza2"
needs_review
```

Do not automatically perform high-risk actions based solely on classification.

---

# 8. Structured Data Extraction

Create document-type-specific extraction schemas.

### Quotation Example

```json id="3a5r4s"
{
  "supplier_name": "",
  "quotation_number": "",
  "quotation_date": "",
  "currency": "",
  "payment_terms": "",
  "delivery_terms": "",
  "valid_until": "",
  "items": []
}
```

### Invoice Example

```json id="c4g3d2"
{
  "supplier_name": "",
  "invoice_number": "",
  "invoice_date": "",
  "due_date": "",
  "currency": "",
  "subtotal": null,
  "tax": null,
  "total": null
}
```

### Contract Example

```json id="v9xsg4"
{
  "parties": [],
  "effective_date": "",
  "expiry_date": "",
  "payment_terms": "",
  "renewal_terms": "",
  "key_obligations": []
}
```

Schemas must be extensible.

Do not store arbitrary AI JSON without validation.

---

# 9. Extraction Templates

Create:

```text id="9yrklq"
AI → Document Intelligence → Templates
```

Authorized administrators can define controlled extraction templates.

Each template contains:

- Name
- Document type
- Structured schema
- Required fields
- Optional fields
- Validation rules
- Version
- Status

AI-generated extraction templates must start as drafts.

Do not allow arbitrary executable instructions.

---

# 10. Extraction Validation

After extraction:

```text id="d4qowu"
AI Extraction
↓
Schema Validation
↓
Data Type Validation
↓
Business Rule Validation
↓
Confidence Check
↓
Ready / Needs Review
```

Examples:

- Date format valid
- Amount numeric
- Currency recognized
- Required fields present
- Total equals item totals where applicable

Do not silently "fix" critical values.

Flag discrepancies for review.

---

# 11. Human Review Interface

Create a document review screen.

Show:

```text id="0hct75"
Document Preview
        |
        |
Extracted Fields
        |
        |
Confidence / Validation
```

Users can:

- Accept value
- Edit value
- Reject value
- Mark document reviewed

Where practical, show source evidence/page reference.

Do not auto-accept low-confidence critical fields.

---

# 12. Extraction Evidence

Store evidence references for extracted values where supported.

Example:

```text id="r8t4ag"
Supplier Name
→ Page 1
→ Extracted text region/reference
```

Avoid storing huge duplicate OCR payloads per field.

Use document/page/offset references where practical.

---

# 13. Document Q&A

Allow:

```text id="kpe0g6"
Ask This Document
```

Examples:

> What are the payment terms?

> Who is the supplier?

> Summarize this contract.

> What are the termination clauses?

Q&A should use:

- Authorized document
- Extracted text
- Relevant chunks only
- Document-specific context

Do not automatically include unrelated company documents.

---

# 14. Multi-Document Comparison

Create:

```text id="glt61m"
AI → Document Intelligence → Compare
```

Allow users to select multiple authorized documents.

Initial comparison use cases:

- Supplier quotations
- Contracts
- Invoices
- Purchase documents

Example:

```text id="9uk7ec"
Compare Quotations

Supplier A
Supplier B
Supplier C

Compare:
Price
Delivery
Payment Terms
Validity
Warranty
```

AI should produce structured comparison output.

Do not invent missing values.

Mark unavailable information clearly.

---

# 15. Quotation Comparison

Create specialized quotation comparison.

Allow:

```text id="9z7s5c"
Quotation A
Quotation B
Quotation C
```

Compare:

- Supplier
- Currency
- Total
- Item prices
- Delivery terms
- Payment terms
- Validity
- Notes

Normalize currencies only if an authorized exchange-rate source exists.

Do not fabricate currency conversions.

---

# 16. Document Search

Extend document search.

Support:

```text id="oq7bnh"
Keyword Search
Semantic Search
Metadata Filters
Document Type
Date Range
Related Module
Supplier
Status
```

Integrate with F11 Knowledge/RAG where appropriate.

Document search access must enforce document permissions.

---

# 17. Semantic Document Search

Allow:

> Find contracts with automatic renewal clauses.

The system may:

```text id="xg9qj9"
Query
↓
Authorized Document Retrieval
↓
Relevant Chunks
↓
AI/RAG Response
```

Do not search documents outside the user's authorization.

---

# 18. Document Knowledge Integration

Allow approved documents to become knowledge sources.

Flow:

```text id="zhqtxc"
Document
↓
Processing
↓
Approved for Knowledge
↓
Chunking
↓
Embedding
↓
Knowledge Collection
```

Document Intelligence and Knowledge should share processing components where practical.

Do not automatically expose every uploaded ERP document to global AI knowledge.

---

# 19. Document Relationships

Support links between:

```text id="k5r8ye"
Document
↔ Supplier
↔ Purchase Requisition
↔ RFQ
↔ Quotation
↔ Purchase Order
↔ Contract
↔ Invoice
```

Relationships may be:

```text id="chppyd"
manual
suggested
verified
```

AI suggestions must be reviewable.

Do not automatically attach a document to a financial/business record based solely on model output.

---

# 20. Duplicate Detection

Implement document duplicate detection.

Use safe indicators:

- File hash
- Similar filename
- Similar extracted content
- Similar metadata

Statuses:

```text id="0lgflx"
unique
possible_duplicate
duplicate
```

Do not automatically delete duplicates.

---

# 21. Document Version Detection

Where documents are related, support:

```text id="ugv1ty"
Contract v1
Contract v2
Contract v3
```

AI may suggest version relationships based on similarity.

Human review required before confirming important relationships.

---

# 22. Document Expiry Monitoring

For supported document types such as contracts:

Extract:

- Effective date
- Expiry date
- Renewal date

Allow controlled reminders:

```text id="p2q0t2"
90 Days Before
60 Days Before
30 Days Before
```

Use F13/F18 scheduler/workflow infrastructure.

AI extraction must be reviewed according to confidence/policy before critical reminders are trusted.

---

# 23. Document Intelligence Actions

Allow AI to propose ERP actions from reviewed extraction.

Example:

```text id="w9xj4f"
Quotation Extracted
↓
User Reviews
↓
Create Draft Supplier Quote
↓
Pending Action
↓
User Confirmation
↓
ERP Record Created
```

Never automatically create ERP records directly from unreviewed AI extraction.

Reuse F9 confirmation.

---

# 24. Create ERP Draft From Document

Allow authorized users to request:

> Create a draft requisition from this quotation.

Flow:

```text id="8g5n4a"
Document
↓
Reviewed Extraction
↓
Mapping Preview
↓
Validation
↓
Pending Action
↓
Confirmation
↓
Create Draft
```

Display field mapping before execution.

---

# 25. Document Summaries

Generate:

- Short summary
- Executive summary
- Key terms
- Risks/attention points
- Important dates
- Missing information

Summaries must distinguish:

```text id="eon2dy"
Extracted Fact
AI Interpretation
Potential Risk
```

Do not present speculation as extracted fact.

---

# 26. Contract Intelligence

Provide optional specialized contract analysis.

Identify:

- Parties
- Dates
- Renewal terms
- Termination clauses
- Payment terms
- Obligations
- Notice periods

Output should be:

```text id="wbs0me"
Extracted Clauses
Summary
Attention Items
Needs Human Review
```

Do not provide legal conclusions as authoritative legal advice.

---

# 27. Invoice Intelligence

Provide controlled invoice extraction.

Extract:

- Supplier
- Invoice number
- Dates
- Amounts
- Currency
- Tax

Validate against known ERP data where authorized.

Example:

```text id="gx1zvv"
Supplier Name
Extracted: ABC Trading
ERP Match: Possible Match
Confidence: 87%
```

Do not automatically approve invoices.

---

# 28. Supplier Document Intelligence

Allow document-based supplier analysis.

Examples:

- Extract supplier contact information
- Identify certifications
- Compare submitted documents
- Detect missing required documents

Use deterministic rules for mandatory document checks where possible.

AI can assist with unstructured content.

---

# 29. Batch Processing

Support batch document processing.

Example:

```text id="m1y1ft"
25 Supplier Quotations
↓
Queue
↓
Process Sequentially/Controlled Concurrency
↓
Review Results
```

Implement:

- Queue limits
- Concurrency limits
- Retry policy
- Failure tracking

Respect F16 usage budgets and rate limits.

---

# 30. Processing Queue

Create:

```text id="psukxr"
AI → Document Intelligence → Processing Queue
```

Show:

- Queued
- Processing
- Ready
- Needs Review
- Failed

Allow safe actions:

- Retry
- Cancel queued job
- View failure summary

Do not blindly retry non-idempotent ERP writes.

---

# 31. Processing Provider Abstraction

Create interfaces for:

```text id="t8tqwb"
Text Extraction Provider
OCR Provider
Vision Provider
AI Analysis Provider
Embedding Provider
```

Reuse AI provider architecture from earlier phases.

Do not couple document processing directly to one provider.

---

# 32. File Security

Mandatory checks before processing:

- Authorization
- Company ownership
- File type
- File size
- Allowed extension
- Safe MIME validation
- Existing malware/file security integration where available

Do not trust file extensions alone.

Never execute uploaded files.

---

# 33. Sensitive Document Controls

Allow policies for sensitive document categories.

Example:

```text id="r7edpt"
identity_document
financial
confidential_contract
restricted
```

Policies may control:

- AI processing allowed
- External provider allowed
- Masking required
- Retention
- Export
- Sharing

Integrate with F16 data governance.

---

# 34. External AI Provider Data Controls

Before sending document content externally:

Check:

```text id="j66z7r"
Company Policy
Document Classification
Sensitive Data Policy
Provider Policy
User Authorization
```

If external processing is prohibited:

- Block request, or
- Use approved local/provider alternative if available.

Do not silently send restricted documents.

---

# 35. Document Retention

Integrate with existing ERP retention and F16 AI retention.

Separate:

- Original file retention
- Extracted text retention
- OCR retention
- AI analysis retention
- Temporary processing data

Do not delete original ERP documents unless existing document policy explicitly permits it.

---

# 36. Document Audit Logging

Audit:

```text id="a5b58g"
Document Registered
Processing Started
OCR Completed
Classification Suggested
Extraction Completed
Extraction Reviewed
Document Linked
Knowledge Indexed
Comparison Generated
ERP Draft Proposed
Action Confirmed
```

Do not store full sensitive document contents in audit logs.

---

# 37. Permissions

Add only if missing:

```text id="6d7j1c"
ai.documents.view
ai.documents.upload
ai.documents.process
ai.documents.review
ai.documents.compare
ai.documents.ask
ai.documents.manage_templates
ai.documents.create_actions
```

Every operation must also respect underlying document/module permissions.

Document Intelligence must never become a bypass around existing file permissions.

---

# 38. Management Center Integration

F16 Management should show:

- Processing queue health
- OCR provider status
- Failed documents
- Processing volume
- AI usage
- Estimated costs
- Sensitive document policy violations

Administrators should see operational metadata, not automatically unrestricted document contents.

---

# 39. Kill Switches

Add server-side controls:

```text id="en9p1g"
Disable Document Processing
Disable OCR
Disable External AI Document Analysis
Disable Document Q&A
Disable Document Comparison
Disable Document-to-ERP Actions
```

Existing documents remain accessible according to ERP permissions.

---

# 40. Performance Requirements

- Processing must be asynchronous.
- Large files must not block web requests.
- Extracted text should be reusable.
- Avoid repeated OCR for unchanged documents.
- Use hashes to detect unchanged files.
- Queue processing must respect concurrency limits.
- Search/indexing should be incremental.
- Large document AI context must be chunked.

---

# 41. Security Requirements

Mandatory:

- Tenant isolation
- Document permission enforcement
- File validation
- No file execution
- Sensitive document policies
- External provider data controls
- Masking before AI where required
- No automatic ERP record creation
- Human review for critical extraction
- F9 action confirmation
- Audit logging
- Retention controls
- Server-side kill switches

---

# Important Constraints

Do NOT implement:

- Automatic invoice approval
- Automatic contract approval
- Legal advice engine
- Automatic deletion of duplicate documents
- Automatic attachment of AI-guessed records
- Unrestricted external document sharing
- Sending restricted documents to external AI silently
- Synchronous processing of large documents
- Arbitrary file execution

This step is:

**A controlled AI Document Intelligence system that turns business documents into searchable, reviewable, structured information without treating AI extraction as unquestionable truth.**

---

# Completion Criteria

AI F20 is complete when:

- Document Intelligence Center exists.
- Document registry works.
- Async processing works.
- Text extraction architecture exists.
- OCR architecture exists.
- Classification works.
- Structured extraction works.
- Validation works.
- Human review works.
- Document Q&A works.
- Multi-document comparison works.
- Semantic search works.
- Knowledge integration works.
- Relationships work.
- Duplicate detection works.
- Expiry monitoring works.
- ERP draft proposals require confirmation.
- Batch processing works.
- Sensitive document policies work.
- Provider abstraction works.
- Queue monitoring works.
- Permissions and tenant isolation work.
- F16 governance integration works.

After implementation, provide:

- Files created
- Files modified
- Database changes
- Processing pipeline
- Provider abstraction
- Supported extraction schemas
- OCR approach
- Human review flow
- Document Q&A architecture
- Comparison architecture
- ERP mapping/action flow
- Sensitive data controls
- Queue architecture
- Security controls
- Known limitations