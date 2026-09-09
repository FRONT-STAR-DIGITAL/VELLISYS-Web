# Folio

Annual branded books for Ugandan SMEs - quotations, invoices, receipts, expenses and headed correspondence in the company’s own logo and colour. One desk, everything included. Montserrat throughout (bundled, so it works offline).

This is a **PHP + MySQL** desk meant to run on **XAMPP**. There is no Node or Next.js. Copy the folder, start Apache and MySQL, open the installer.

## What you get

- Sign-in with a **show password** control. Company desk: `accounts@ofagros.org` / `folio2026`
- **Platform admin** at `admin@folio.ug` / `folio-admin-2026` - create companies, issue desk logins, set stationery, onboard, mark live
- **UGX or USD** on each document (default in Settings)
- **CSV export** on invoices, quotations, receipts, expenses, correspondence, debtors, creditors, clients, reports, and on a single document’s lines
- Add as many line items as you need when creating invoices, quotations, receipts and expenses. Quantity steps in whole numbers and still accepts decimals
- Printed documents show item/description, quantity, unit, unit price and full price
- Static navigation; portal background is a plain diagonal-line pattern
- **Correspondence** stays fully editable. Settings → Templates picks a document design (ledger, bills, twin copy, stripe, plus Estate cream and Lake night)
- Expenses as cards; debtors and creditors as tables with totals
- Date filters: today, this week, last week, this month, last month, plus from/to
- Reports with a time series, expense pie and debtors aging bars
- VAT 18% on taxed lines for every company
- Email sends **From** the signed-in account
- No stock module, no plan packages, no EFRIS box on documents

## Install on XAMPP (Windows)

1. Install [XAMPP](https://www.apachefriends.org/) and start **Apache** and **MySQL**.
2. Copy this entire folder to:

   `C:\xampp\htdocs\folio`

3. Open a browser:

   `http://localhost/folio/install.php`

   That creates the `folio` database, tables, Ofagros demo data, the company login and the platform admin.

4. Sign in at `http://localhost/folio/login.php`.

Default MySQL in XAMPP is user `root` with an empty password. If you set a password, edit `config/database.php`.

### phpMyAdmin (optional)

Create a database named `folio`, then import `sql/schema.sql`. If the `users` table is empty, still run `install.php` so the demo user and seed documents are created.

### Email on XAMPP

PHP `mail()` needs Mercury (bundled with some XAMPP builds) or an SMTP relay. If send fails, Folio still **logs the email on the document** as queued. On a live host, ordinary PHP mail usually works.

Print / PDF uses the browser print dialog (Save as PDF). CSV downloads from the Export CSV buttons.

## Local PHP (without XAMPP)

```bash
# MySQL/MariaDB must be running. Then:
php install.php
php -S 127.0.0.1:43219 -t . router.php
```

Open http://127.0.0.1:43219/login.php

You can override connection details:

```bash
export FOLIO_DB_HOST=127.0.0.1
export FOLIO_DB_NAME=folio
export FOLIO_DB_USER=root
export FOLIO_DB_PASS=
```

## Git

Commit this folder as-is. Uploaded logos live in `uploads/logos/` and are gitignored except `.gitkeep`.
