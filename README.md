# Folio

Annual branded books for Ugandan SMEs — quotations, invoices, receipts, expenses and headed letters in the company’s own logo and colour.

This is a **PHP + MySQL** desk meant to run on **XAMPP**. There is no Node or Next.js. Copy the folder, start Apache and MySQL, open the installer.

## What you get

- Sign-in page. Demo account: `accounts@ofagros.org` / `folio2026`
- Branding colour on the **whole chrome**, including the left navigation bar, buttons and invoice bars
- Documents with actions: view, print/PDF, email, convert quotation → invoice, take a receipt, void
- Click a client to see invoices, quotations, receipts, letters and expenses, plus buttons to add each
- **Quick add** (top right) for invoice, quotation, receipt, expense, letter or client
- Email sends **From** the signed-in account
- SME VAT 18% and a demo EFRIS fiscal mark (not live URA accreditation)
- Tight reports: P&amp;L, VAT, debtors aging, expenses by category, cash book
- No stock module

Plans (this demo does not take payment): Starter UGX 150,000 / year · SME UGX 250,000 · Office UGX 350,000.

## Install on XAMPP (Windows)

1. Install [XAMPP](https://www.apachefriends.org/) and start **Apache** and **MySQL**.
2. Copy this entire folder to:

   `C:\xampp\htdocs\folio`

3. Open a browser:

   `http://localhost/folio/install.php`

   That creates the `folio` database, tables, Ofagros demo data and the login account.

4. Sign in at `http://localhost/folio/login.php`.

Default MySQL in XAMPP is user `root` with an empty password. If you set a password, edit `config/database.php`.

### phpMyAdmin (optional)

Create a database named `folio`, then import `sql/schema.sql`. If the `users` table is empty, still run `install.php` so the demo user and seed documents are created.

### Email on XAMPP

PHP `mail()` needs Mercury (bundled with some XAMPP builds) or an SMTP relay. If send fails, Folio still **logs the email on the document** as queued. On a live host, ordinary PHP mail usually works.

Print / PDF uses the browser print dialog (Save as PDF).

## Local PHP (without XAMPP)

```bash
# MySQL/MariaDB must be running. Then:
php install.php
php -S 127.0.0.1:43219 router.php
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
