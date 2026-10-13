# Cleanup & Completion Plan — ERP `was`

**Goal:** finish every deliberately-deferred item, then strip all static / non-working / broken / duplicate weight, leaving only a meaningful working pipeline.

**Non-negotiables:** a restore point before any deletion · verify zero references before removing a file · re-run the full page sweep after every phase · never leave test data behind.

---

## Phase 0 — Safety net (must complete first)

- [ ] 0.1 Commit the current working tree as a checkpoint so every later deletion is revertible.
- [ ] 0.2 Export the live schema (454 tables) to `database/schema.sql` and un-ignore that path, so the repo can rebuild itself. Today the entire data model exists only inside MySQL.
- [ ] 0.3 Record baseline counts (tables, modules, files, journal entries, GL lines) to compare against after cleanup.

## Phase 1 — Evidence-based inventory

Classify every candidate with proof (reference counts, not vibes). Nothing is deleted on suspicion.

- [ ] 1.1 **Corrupt / mock files** — `modules/finance/PayrollProcessor.php`, `PayrollReportGenerator.php` (0-line escaped-string mocks), `modules/finance/helpers/finance_helpers.php.backup` (invalid PHP), `fetchColumn()` (0 bytes), extension-less prompt dumps `F-10`, `F-10-test`, `f9`, `THEMES`, `FINANCE/F7`, `FINANCE/F7-testing`.
- [ ] 1.2 **Scratch directories** — `FINANCE/` (44 `.mjs` probes, `_probe*.php`, 194 KB `versions.json`), `AI-Assistance-Module/` (34 markdown specs, zero PHP), `Inventory/`, `Procurement/` (markdown only), `Error/`, `.freebuff/`.
- [ ] 1.3 **Unreferenced root scripts** — 41 of 46 root PHP files have zero inbound references. Split into: keep (cron, real infra) vs delete. Every one is currently web-reachable with no auth check.
- [ ] 1.4 **Dead code** — `helpers/menu.php` (zero callers), `templates/header.php` (zero includes), `modules/clients/` (now three redirect stubs with zero inbound links), `modules/payroll/` (single stub `config.php`).
- [ ] 1.5 **Duplicate pairs** — `finance/payroll` vs `finance/payroll_finance`; `activity_logs` vs `audit_logs`; `finance/{purchase_orders,goods_receipt,vendor_bills,vendor_payments}` vs `modules/procurement/`. Decide keeper per pair with proof.
- [ ] 1.6 Confirm what still references `sidebar_menu` before touching `menu_manager`.

## Phase 2 — Dynamic configuration, no static values left

- [ ] 2.1 **Settings admin** (`modules/settings/`) — edit `app_settings` by group: branding, finance, formats. Permission-gated, CSRF, audit-logged.
- [ ] 2.2 **Currency admin** — manage `currencies` + add dated `currency_rates`; show the effective rate and its date.
- [ ] 2.3 **Document sequence admin** — per company, per type: prefix, padding, next number; safe manual renumber with an explicit warning.
- [ ] 2.4 **Lookup tables** — replace hardcoded PHP option arrays with `lookup_types` + `lookup_values`, seeded from the current arrays so behaviour is unchanged. Company-scopable.
- [ ] 2.5 **Chart of accounts company assignment** — column exists and the picker filters on it, but no UI. Add it to `chart_of_accounts`.
- [ ] 2.6 Move DB credentials out of `config/database.php` into a git-ignored local file, with an example template committed.

## Phase 3 — Working numbering pipeline

- [ ] 3.1 Route every generator through `nextDocumentNumber()`: customer invoices, credit notes, receipts, journals, POs, requisitions, goods receipts, vendor bills, customers, vendors, invoices.
- [ ] 3.2 Replace the `COUNT(*)+1` race in `modules/invoices/create.php` and `modules/clients`-era generators.
- [ ] 3.3 Seed each sequence's `next_number` from existing rows so no number is ever reused or collides.

## Phase 4 — Fix broken logic

- [ ] 4.1 `tickets.sla_breached` is read in four places and written by nothing — add a real writer.
- [ ] 4.2 `modules/audit_logs/index.php` has no permission check at all.
- [ ] 4.3 `modules/finance/customer_invoices/create.php` invoice number still bypasses the company prefix.
- [ ] 4.4 Remove the fake `audit.view` permission string in the sidebar (real one is `auditlogs.view`).
- [ ] 4.5 Fix `curve: 'curve'` invalid Carbon option in the five remaining dashboards.

## Phase 5 — Remove the weight

Only after Phase 1 proves zero references. Files are deleted, not blanked; the Phase 0 commit makes it reversible.

- [ ] 5.1 Delete corrupt/mock/scratch files and directories.
- [ ] 5.2 Delete unreferenced one-off root scripts.
- [ ] 5.3 Delete dead code and superseded redirect stubs.
- [ ] 5.4 Collapse duplicate modules to a single keeper, repointing every inbound link.
- [ ] 5.5 Update `AGENTS.md` so it describes what actually exists — it currently documents `config/schema.sql`, migrations 001–047, `FixedAssetService`, and test suites that are all absent.

## Phase 6 — Verification

- [ ] 6.1 `php -l` across every PHP file.
- [ ] 6.2 HTTP sweep of all major pages: no fatal, warning, notice or undefined-key.
- [ ] 6.3 Re-run the company-scoped posting test suite.
- [ ] 6.4 Confirm baseline counts unchanged for live data.
- [ ] 6.5 Grep for references to anything deleted — zero orphans.
- [ ] 6.6 Manual test checklist handed to the user.

---

## Out of scope unless asked

Rotating the hosting DB password and the `admin123` account (only you can do those on the server). Consolidating the two payroll engines into a real calculation module — that is a feature build, not a cleanup, and I will report what I find rather than invent it.
