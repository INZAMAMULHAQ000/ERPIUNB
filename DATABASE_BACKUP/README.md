# Database Backup — erp_db (local XAMPP)

Taken: 2026-10-13 10:12 local · after the Customer Master, Company Master, company-scoped
posting and configuration-layer work.

| File | Size | What it is |
|---|---|---|
| `erp_db_full_20261013_101236.sql` | 2.6 MB | Complete schema + all data, 464 tables, 12,379 rows |
| `erp_db_full_20261013_101236.sql.gz` | 290 KB | Same content, gzip -9 |
| `SHA256SUMS.txt` | — | Checksums for both |

Contents were produced with:
`mysqldump -u root --single-transaction --quick --routines --triggers --events --add-drop-table --default-character-set=utf8mb4 erp_db`

## Verification actually performed

This backup was **test-restored**, not just written:

- Restored into a scratch database → `mysql` exited 0 with no errors.
- **464 / 464 tables** recreated; none missing, none extra.
- **Row counts compared on all 464 tables (12,379 rows) → 0 mismatches.**
- Content hashes matched on `clients`, `companies`, `general_ledger`.
- Column counts confirmed on the widened tables: `clients` 98, `companies` 135,
  `customer_addresses` 21, `document_sequences` 11, `lookup_values` 9, `currencies` 9,
  `app_settings` 9, `currency_rates` 7.
- `lookup_values` 146 rows and `document_sequences` 39 rows present after restore.
- gzip tested and byte-identical on round-trip; SHA256 verified.
- Scratch database dropped afterwards.

The source database contains **no** stored procedures, functions, triggers, views or
events, so their absence from the dump is correct rather than a loss.

## What is inside — treat this file as a secret

It contains real data, including:

- `users.password` — bcrypt hashes for all 14 accounts
- `ai_*` tables — encrypted provider API keys (note: the repo's encryption key is
  hardcoded, so those are effectively readable by anyone holding this file)
- Every customer, company, invoice, journal, GL line and audit record

Store it encrypted, never email it, never commit it. `DATABASE_BACKUP/` is gitignored,
and the dump was confirmed absent from git history.

## Restoring locally

```bash
mysql -u root -e "DROP DATABASE IF EXISTS erp_db_restore; CREATE DATABASE erp_db_restore CHARACTER SET utf8mb4;"
gunzip -c DATABASE_BACKUP/erp_db_full_20261013_101236.sql.gz | mysql -u root erp_db_restore
```

Restore into a **new** database first and check it, rather than overwriting `erp_db` in place.
The dump contains `DROP TABLE` statements, so pointing it at a populated database destroys
that database's tables.

---

# Taking these changes to PRODUCTION — read this first

Your production database (`u690977936_application`) almost certainly has its own live data —
real companies, invoices, journals, users. **Importing this full dump into production would
replace all of it with your local test data.** Two different goals, two different procedures:

## Option A — production already has real data you must keep  (usually correct)

Do **not** import this dump. Run the five idempotent migrations against production instead.
They only add columns and tables, never drop or rewrite existing data, and each one checks
`information_schema` before altering so re-running is safe.

```bash
php config/migrations/2026_10_12_customer_master.php
php config/migrations/2026_10_12_company_master.php
php config/migrations/2026_10_12_company_scoped_posting.php
php config/migrations/2026_10_12_configuration_layer.php
php config/migrations/2026_10_12_sequence_formats.php
```

Back up production **before** running them:

```bash
mysqldump -u <prod_user> -p --single-transaction <prod_db> > prod_before_migrations.sql
```

If you only have cPanel/phpMyAdmin and cannot run PHP, say so — I can generate an equivalent
pure-SQL upgrade script you can paste into the SQL tab.

## Option B — production is empty or disposable

Then the dump is the fast path. Import it into the production database (this destroys
whatever is in it):

```bash
mysql -h localhost -u <prod_user> -p <prod_db> < erp_db_full_20261013_101236.sql
```

The dump targets whatever database you select, so it is not tied to the name `erp_db`.

## Either way, after deploying

1. **Change the admin password.** `admin@erp.com / admin123` is a demo credential printed on
   the login page.
2. **Rotate the production DB password** — the current one is committed in plaintext at
   `config/database.php:95`.
3. Re-check the company switcher: production users need `user_companies` rows, otherwise
   non–Super Admin users will see no company (role id 1 bypasses; everyone else does not).
4. Verify each active production company has a `financial_years` row and periods — the
   `company_scoped_posting` migration provisions them from `financial_year_start`, falling
   back to `app_settings.fiscal_year_start_month`.
