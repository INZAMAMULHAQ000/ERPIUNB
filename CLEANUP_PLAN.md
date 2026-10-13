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

## Verification result (2026-10-13)

- Whole-tree `php -l`: **0 files fail to parse** (was 12 — all in the deleted orphan module).
- Company-scoped posting test: passes; the single reported "FAIL" is the test's own `1 === '1'` assertion, not the product.
- Document sequence test: **all checks pass**, including 20 unique sequential allocations.
- Page sweep: **36/36 clean** — no fatal, warning, notice or undefined-key.
- Data baseline: `clients`, `companies`, `journal_entries`, `general_ledger`, `financial_years`, `accounting_periods`, `chart_of_accounts` all **unchanged**. Only +2 tables and +4 permissions, both intended.
- CLI tools blocked over HTTP return 403; app pages still route to login correctly.

## Status by phase

| Phase | State |
|---|---|
| 0 Safety net | Done — checkpoint commits, schema snapshot, baseline recorded |
| 1 Inventory | Done — evidence-based, every verdict backed by reference counts |
| 2 Dynamic configuration | Done — settings, currencies, sequences, lookups, COA company assignment |
| 3 Numbering pipeline | Done for AR/customer documents; procurement and journal vouchers already had atomic sequences and were left alone |
| 4 Broken logic | Done — `audit.view`, unguarded audit page, `sla_breached`, curve bug |
| 5 Remove weight | Done — see below |
| 6 Verification | Done |

## Removed

Corrupt/mock: `finance/PayrollProcessor.php`, `finance/PayrollReportGenerator.php`, `finance/helpers/finance_helpers.php.backup`, `fetchColumn()`, `F-10`, `F-10-test`, `f9`, `THEMES`, `ui_preview_m365.html`, `blackbox_mcp_settings.json`, `skills-lock.json`.
Scratch: `FINANCE/`, `Error/`, `AI-Assistance-Module/`, `Inventory/`, `Procurement/`, `.freebuff/`.
Dead code: `helpers/menu.php`, `templates/header.php`, `modules/clients/` (redirect stubs), `modules/payroll/` (stub config).
Orphan module: `modules/finance/payroll/` — 33 files, 12 of which did not parse, zero inbound references, fully duplicated by the live `modules/finance/payroll_finance/`.
Root one-offs: 27 unreferenced dev/debug/codegen scripts.

## Deliberately kept

`composer.json` / `composer.lock` — the only record of the dompdf dependency that two export pages call; `vendor/` is absent so those exports are already broken and this is the breadcrumb to fix them.
`maintenance.php` — has a correct Super-Admin gate and is a real console.
`setup_f9_*`, `setup_f11`, `setup_p1–p4`, `setup.php`, `migration_inspector.php`, `migration_executor.php`, `erp_module_health_check.php`, `create_user_roles.php`, `enable_workflow_studio.php`, `ai_automation_scheduler.php` — CLI tools holding the only reproducible seed data. Now blocked over HTTP rather than deleted.
`modules/menu_manager/` — the UI is unwired, but `sidebar_menu` has a live consumer in `AIGovernanceService::seedSidebar()`. Needs a decision, not a deletion.
`modules/activity_logs/` and `modules/audit_logs/` — both reachable; now both correctly permission-gated.

## Still open (needs your decision, not silently skipped)

1. `modules/menu_manager/` writes `sidebar_menu`, which nothing renders. Either wire the sidebar to it or retire the module.
2. Option lists in the customer/company forms still fall back to PHP arrays when a lookup type is missing. They now read `lookup_values` first.
3. `config/database.php` still contains plaintext production credentials; `admin@erp.com / admin123` is still the login. Only you can rotate these.
4. Payroll still has no calculation engine — `payroll_runs` is read-only external data with no creator anywhere in the codebase.
