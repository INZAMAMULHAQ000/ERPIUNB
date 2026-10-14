# newdump/ — production database backup and schema reference

Taken **2026-10-14 15:08** local from `erp_db`, after the contract-terms work: inline engagement
on the contractor master form, contract paperwork, and the employee contract block.

| File | Size | Purpose |
|---|---|---|
| `erp_db_production_20261014_150855.sql` | 2,578 KB | **Use this.** Full schema + all data: 466 tables, 12,460 rows, 6,082 columns |
| `erp_db_production_20261014_150855.sql.gz` | 287 KB | Same content compressed — use if the upload limit blocks the `.sql` |
| `DATABASE_STRUCTURE.md` | 557 KB | Full data dictionary: every table, every column, keys, defaults, FK targets, row counts |
| `generate_structure.php` | | Regenerates `DATABASE_STRUCTURE.md` from the live database |
| `SHA256SUMS.txt` | | checksums for every dump, the compressed copies and the dictionary |
| `erp_db_production_20261014_100252.sql` (+`.gz`) | | Superseded — taken before the contract-terms work |
| `erp_db_production_20261013_163751.sql` (+`.gz`) | | Superseded — before the contractor module |
| `erp_db_production_20261013_110452.sql` (+`.gz`) | | Superseded — before the employee identification work |

## What this dump adds over the 10:02 one

Created by `config/migrations/2026_10_14_employee_contract_terms.php`:

- **`employees.contract_number`, `contract_start_date`, `contract_end_date`** (+ two indexes) — the
  employment itself is a fixed-term instrument, so expiry reporting now covers the contract and not
  only the visa. A fourth scan slot, **Employment Contract Copy**, sits on the employee form beside
  passport / visa / Emirates ID.
- **Five contract document types** in `contractor.document_type`: `contract_copy`, `sow`,
  `amendment`, `renewal_letter`, `termination_letter` — filed against a contract through
  `contractor_documents.contract_id`, uploaded and listed from the contract card.
- UI changes alongside (no DDL): contract term fields now appear **inline on the contractor master
  form**, so one save registers the person and opens a draft contract, and both profiles colour
  every document by its expiry.

Contractor-module content from the earlier dump is unchanged (`contractors` 39 columns,
`contractor_contracts` 47, `contractor_rate_schedules` 11, `contractor_documents` 12,
`people.contractor_id`, `CTR…` / `CTRT-…` numbering, 5 `contractors.*` permissions).
`database/schema.sql` in the repo was regenerated from the same live database.

## DATABASE_STRUCTURE.md — the tracking document

468 tables listed by domain (Contractor, Employee & HR, Finance & GL, Procurement & Vendors,
Sales & CRM, Projects, Inventory, Helpdesk, AI, Configuration, System, Other), each with a column
table: position, name, type, nullability, key flags (`PK`/`UQ`/`IDX`/`FK`), default and
foreign-key target, plus live row counts and an anchored table index.

Regenerate it any time — it reads `information_schema` only and writes nothing to the database:

```bash
php newdump/generate_structure.php
```

The dictionary describes the **live** database (468 tables, 6,111 columns, 12,469 rows) while the
dump carries **466 / 6,082 / 12,460**. The difference is exactly the two local scratch tables below
(29 columns, 9 rows), so the two documents reconcile rather than contradict. Re-run the generator
after every migration and diff the output.

## Why the scratch tables are excluded

`clients_pre_customer_master_20261012` and `companies_pre_company_master_20261012` are local
pre-migration safety nets, not business data. Zero references to them appear in the dump.
Everything else is identical to the live database.

## Command used

```
mysqldump -u root --single-transaction --quick --routines --triggers --events \
  --add-drop-table --default-character-set=utf8mb4 --comments \
  --ignore-table=erp_db.clients_pre_customer_master_20261012 \
  --ignore-table=erp_db.companies_pre_company_master_20261012 erp_db
```

`--single-transaction` gives a consistent InnoDB snapshot without locking the running app.

## Verification actually performed (25 checks, 0 failures)

Not just written — restored into a scratch database and compared with the live one:

- Restore exited `0` with a completely empty stderr.
- **466 / 466 tables**; table sets identical in **both** directions (nothing missing, nothing extra).
- **Per-table row counts compared individually: 12,460 source vs 12,460 restored, 0 mismatched
  tables.** The query reports "466 pairs compared" so a silently failing comparison cannot
  masquerade as a pass, and the harness is self-tested against a deliberately wrong pair every run.
- Every source column present in the restored schema (anti-join = 0). That check is what exposed the
  10:02 dump as stale: it reported 3 missing columns and 2 mismatched tables — exactly the employee
  contract block this dump now carries.
- ID-set MD5 matches on `contractors`, `contractor_contracts`, `contractor_rate_schedules`,
  `contractor_documents`, `employees`, `employee_documents`, `people`.
- Contractor structure survived: column counts, index counts, all foreign keys, and the
  `uq_one_current_contract` unique index that enforces one current contract per contractor.
- `people.contractor_id` present; the real upload paths for employee #34 preserved.
- Zero scratch tables in the restore; gzip `gzip -t` clean and byte-identical when decompressed.
- Scratch database dropped afterwards.

The source has no stored procedures, functions, triggers, views or events, so their absence is
accuracy rather than loss.

## IMPORTANT — uploaded files are not in this dump

`employees.photo_path`, `contractors.photo_path` and both `*_documents.file_path` columns store
paths only; the bytes live on disk under `uploads/`. Importing the SQL alone leaves every scan and
photo broken.

Currently on disk (3.2 MB, all belonging to employee #34):

```
uploads/employees/34/photo_34_68453e6b.jpg             1,993,870
uploads/employees/34/passport_copy_34_b581069b.pdf        760,255
uploads/employees/34/emirates_id_copy_34_c007d789.pdf     383,747
uploads/employees/34/visa_copy_34_eabae5c8.jpg            121,860
```

`uploads/contractors/` is empty — the register has no rows yet, because every record created during
testing was deleted before this dump. Confirm with `ls -R uploads` after importing.

Copy the tree to the production app root (the folder holding `modules/`), preserving names exactly —
the database references them verbatim, and `uploads/` is gitignored so a git deploy will not carry it:

```bash
scp -r uploads/ <user>@<prod>:/path/to/app/uploads/
```

## Importing to production

The dump has no `CREATE DATABASE` / `USE` statement, so it imports into whichever database you
select — it does not have to be named `erp_db`.

```bash
cd newdump && sha256sum -c SHA256SUMS.txt          # verify the transfer first

# 1. back up production first so you can roll back
mysqldump -h localhost -u <prod_user> -p <prod_db> > prod_before_import.sql

# 2. import (this DROPS and recreates every table in <prod_db>)
mysql -h localhost -u <prod_user> -p <prod_db> < erp_db_production_20261014_150855.sql
```

In phpMyAdmin: select the production database → Import → choose the `.sql` (or `.gz`) → Go.

## After importing — do these five things

1. **Change the admin password.** The imported `users` table still carries the local demo account
   `admin@erp.com / admin123`, and the login page prints those credentials.
2. **Copy `uploads/`** as described above, or document viewers show broken images and blank PDFs.
3. **Make `uploads/` writable** by the web-server user. The modules create
   `uploads/employees/<id>/` and `uploads/contractors/<id>/` on first upload and report a clear
   error if the directory cannot be made.
4. **Assign companies.** Non-Super-Admin users need `user_companies` rows or they see no company
   at all; role id 1 bypasses that check, everyone else does not. Contractor contracts are stamped
   with the active company and numbering (`CTR…`, `CTRT-…`) is per company — check the counters
   under Configuration → Document Numbering before the first live entry.
5. **Rotate the DB password** committed in plaintext at `config/database.php:95`, and confirm
   production resolves to the intended credential branch (it is chosen by sniffing `HTTP_HOST`).

## Sensitivity

This folder holds live personal data: bcrypt hashes for 14 accounts, an employee record with real
passport and Emirates ID numbers, all customer, company, invoice, journal and audit records, and
the `ai_*` tables with encrypted provider API keys — effectively readable by anyone holding the
file, because the repo's encryption key is hardcoded in `modules/ai/includes/ai_credential.php`.

`DATABASE_STRUCTURE.md` is the schema only — no rows — but it does disclose table and column
naming across the whole ERP.

`newdump/` is gitignored and confirmed absent from git history. Store it encrypted, delete it from
any shared machine once deployed, and never email it. The companion `uploads/employees/34/` tree is
worse still: actual passport and visa images of a named person.
