# newdump/ — production database backup and schema reference

Taken **2026-10-15 08:54** local from `erp_db`. This is the production-ready pairing of the People
Management multi-role redesign with the access and security hardening pass that followed it.

| File | Size | Purpose |
|---|---|---|
| `erp_db_production_20261015_085453.sql` | 2,582 KB | **Use this.** Full schema + all data: 467 tables, 12,533 rows, 6,090 columns |
| `erp_db_production_20261015_085453.sql.gz` | 294 KB | Same content compressed — use if the upload limit blocks the `.sql` |
| `DATABASE_STRUCTURE.md` | 558 KB | Full data dictionary: every table, every column, keys, defaults, FK targets, row counts |
| `generate_structure.php` | | Regenerates `DATABASE_STRUCTURE.md` from the live database |
| `SHA256SUMS.txt` | | checksums for the current dump, its `.gz`, this README, the dictionary and the generator |
| `erp_db_production_20261014_165508.sql` (+`.gz`) | | Superseded — multi-role schema, before the Admin grants |
| `erp_db_production_20261014_150855.sql` (+`.gz`) | | Superseded — taken before the multi-role work |
| `erp_db_production_20261014_100252.sql` (+`.gz`) | | Superseded — taken before the contract-terms work |
| `erp_db_production_20261013_163751.sql` (+`.gz`) | | Superseded — before the contractor module |
| `erp_db_production_20261013_110452.sql` (+`.gz`) | | Superseded — before the employee identification work |

## What this dump carries

Schema, from `config/migrations/2026_10_14_people_multi_role.php` (additive, idempotent, nothing
removed):

- **`person_roles`** (8 columns, 2 rows) — one row per role a person holds: `role`
  (`employee` / `customer` / `contractor`), `status` (`pending` / `complete` / `declined`),
  `record_id` and `completed_at`. `UNIQUE (person_id, role)` makes a double role association
  impossible at the database level, and the FK to `people` is `ON DELETE CASCADE`, so removing a
  directory entry cannot leave orphan role rows. These rows **are** the onboarding workflow state:
  a refresh, an abandoned step, a failed save or a module record deleted elsewhere is answered by
  reading them, not by remembering anything in the session.
- **`people.uq_people_email`** — `people.email` was only indexed, so the directory's "one person
  per email" rule existed in application code alone and two concurrent creations could fork a
  person. The index is now UNIQUE; email stays nullable, so several people recorded without an
  address remain legal. The migration refuses to add it if duplicate emails already exist and
  prints the offending addresses instead of deleting or merging anyone.
- **Backfill**: every existing person got exactly one role row, derived from `category` and the
  populated link columns, marked `complete` where a record already sits behind the link. The two
  live people (ids 4 and 5) therefore appear finished, not half-onboarded.
- `people.category` is **unchanged** and keeps its old meaning as the *primary* role (the first
  selected one), so every existing reader of it — the list filter, the statistics, the profile
  badge — still works. `database/schema.sql` in the repo was regenerated from the same database
  (469 tables).

Data, from `config/migrations/2026_10_15_people_directory_admin_access.php`:

- **Admin (role 2) now holds `people_management.view`, `.create` and `.edit`** — three rows in
  `role_permissions`. `people_management.delete` stays Super Admin only. Without this, an Admin held
  `employees.create` / `customers.create` / `contractors.create` yet could not open the screen that
  starts an onboarding.

Code changes that carry no DDL but ship with this snapshot: People Management no longer inserts into
`employees` or `contractors` itself; `middleware/auth.php` re-validates the session against the
`users` table; `modules/people_management/delete.php` now requires POST plus a CSRF token; and
production database credentials moved out of `config/database.php` into a gitignored
`config/db.local.php`.

No employee, customer or contractor table changed shape. `contractors` (39 columns),
`contractor_contracts` (47), `contractor_rate_schedules` (11), `contractor_documents` (12) and
`people.contractor_id` are exactly as in the earlier dumps.

## DATABASE_STRUCTURE.md — the tracking document

469 tables listed by domain (Contractor, Employee & HR, Finance & GL, Procurement & Vendors,
Sales & CRM, Projects, Inventory, Helpdesk, AI, Configuration, System, Other), each with a column
table: position, name, type, nullability, key flags (`PK`/`UQ`/`IDX`/`FK`), default and
foreign-key target, plus live row counts and an anchored table index.

Regenerate it any time — it reads `information_schema` only and writes nothing to the database:

```bash
php newdump/generate_structure.php
```

The dictionary describes the **live** database (469 tables, 6,119 columns, 12,542 rows) while the
dump carries **467 / 6,090 / 12,533**. The difference is exactly the two local scratch tables below
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

## Verification actually performed (19 checks, 0 failures)

Not just written — restored into a scratch database (`erp_db_verify`) and compared with the live one:

- Restore exited `0` with a completely empty stderr.
- **467 / 467 tables**; table sets identical in **both** directions (nothing missing, nothing extra);
  zero scratch tables in the restore.
- **Per-table row counts compared individually: 467 pairs, 0 mismatched tables, 12,514 source rows vs
  12,514 restored.** The harness prints how many pairs it actually compared and aborts if that number
  is below the table count. That guard is not decorative: the first run of this suite reported
  "0 compared, 0 mismatched" and looked like a clean pass because `subprocess` had been handed
  `'-u root'` as one argument instead of two, so MySQL was asked for a user named `" root"` and every
  table list came back empty. Fixed, then re-run.
- Every source column present in the restored schema (anti-join = 0; restore carries 6,090 columns).
- The new structures survived: `person_roles` 8 columns / 2 rows, `UNIQUE (person_id, role)`, the FK
  reading `people on delete CASCADE` in `information_schema.referential_constraints`, the
  `enum('pending','complete','declined')` status, and `people.uq_people_email`.
- `people` rows identical by ID, category and `employee_id` (`4:employee:17, 5:employee:18`).
- gzip `gzip -t` clean and byte-identical when decompressed; `person_roles` present in the dump text
  as both DDL and data.
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
mysql -h localhost -u <prod_user> -p <prod_db> < erp_db_production_20261015_085453.sql
```

In phpMyAdmin: select the production database → Import → choose the `.sql` (or `.gz`) → Go.

**If you deploy code instead of importing this dump**, the schema change is
`config/migrations/2026_10_14_people_multi_role.php` — run it before the new People screens, which
read `person_roles` unconditionally and will fatal without it.

## After importing — do these six things

1. **Create `config/db.local.php` on the server.** `config/database.php` no longer carries
   production credentials; it reads them from that file and refuses to start without it. Copy
   `config/db.local.example.php`, set the real host/name/user/password and
   `'production' => true`. It is gitignored, so a git deploy will not bring it — and it must be
   `chmod 640` or tighter, owned by the web-server user.
2. **Change the admin password.** The imported `users` table still carries the local demo account
   `admin@erp.com / admin123`, and the login page prints those credentials.
3. **Copy `uploads/`** as described above, or document viewers show broken images and blank PDFs.
4. **Make `uploads/` writable** by the web-server user. The modules create
   `uploads/employees/<id>/` and `uploads/contractors/<id>/` on first upload and report a clear
   error if the directory cannot be made.
5. **Assign companies.** Non-Super-Admin users need `user_companies` rows or they see no company
   at all; role id 1 bypasses that check, everyone else does not. Contractor contracts are stamped
   with the active company and numbering (`CTR…`, `CTRT-…`) is per company — check the counters
   under Configuration → Document Numbering before the first live entry. Admins now hold
   `people_management.view/create/edit` from this dump; if you deploy code instead, run
   `php config/migrations/2026_10_15_people_directory_admin_access.php`.
6. **Rotate the database password that is still in git history.** `config/database.php` carried the
   production password in plaintext up to this change; the file is clean now, but the old value is
   reachable in past commits. Change it in the hosting panel and put only the new one in
   `config/db.local.php`.
6. **Decide who may use People Management.** All four `people_management.*` permissions are granted
   to Super Admin only, while `employees.create`, `customers.create` and `contractors.create` also
   sit with Admin. The onboarding coordinator handles that gracefully — a user who lacks a module's
   permission sees "Needs employees.create" instead of a link that dies on Access Denied — but if
   Admins are meant to create people, grant `people_management.view/create/edit` to that role.

## Sensitivity — read this before you push

This folder holds live personal data: bcrypt hashes for 14 accounts, an employee record with real
passport and Emirates ID numbers, all customer, company, invoice, journal and audit records, and
the `ai_*` tables with encrypted provider API keys — effectively readable by anyone holding the
file, because the repo's encryption key is hardcoded in `modules/ai/includes/ai_credential.php`.

`DATABASE_STRUCTURE.md` is the schema only — no rows — but it does disclose table and column
naming across the whole ERP.

**Status of the repository, checked in this working copy:** `.gitignore` now excludes
`newdump/*.sql`, `newdump/*.sql.gz` and `config/db.local.php`, and the eight previously committed
dump files have been removed from the index (`git rm --cached`), so the next commit stops shipping
them. The files stay on disk. `git ls-files newdump` now lists only `DATABASE_STRUCTURE.md`,
`README.md`, `SHA256SUMS.txt` and `generate_structure.php`.

**What is still exposed:** history. Commits `731a219` and `eea63cd` (both labelled "Backup
completely") carry full dumps — the 10:02 dump alone is a 2,620,110-byte object reachable from
`HEAD` — and this repository has a remote (`origin` → `github.com/INZAMAMULHAQ000/ERPOUIVB`), so
whoever can read that remote can already read the passport numbers, the bcrypt hashes and the AI
provider keys. `config/database.php` likewise carried the production database password in earlier
revisions. Untracking does not undo that, and neither does deleting the files.

Treat these credentials and records as published until history is dealt with. The steps, least to
most invasive — the third one rewrites every commit hash and needs your explicit decision, so it
has not been run:

1. Rotate what is exposed: the admin password, the database password in the hosting panel, and every
   AI provider key held in `ai_*`.
2. Commit the untracking above, and confirm `git ls-files` never re-lists a `.sql`.
3. Purge the blobs from history with `git filter-repo --invert-paths --path-glob 'newdump/*.sql*'`
   (or BFG), then force-push `main` and every branch that carries them
   (`codebase-indexing-0baac`, `erp-module-system`). Everyone else must re-clone; anyone with an old
   clone keeps the data locally regardless.

`DATABASE_STRUCTURE.md` is the schema only — no rows — but it does disclose table and column naming
across the whole ERP.

The companion `uploads/employees/34/` tree is worse still: actual passport and visa images of a
named person. That one *is* gitignored, so it lives only on this disk and on any machine it is
copied to.

Store the folder encrypted, delete it from any shared machine once deployed, and never email it.
