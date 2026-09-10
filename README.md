# Vellisys

Branded books for Ugandan SMEs - quotations, invoices, receipts, expenses and headed correspondence in the company's own logo and colour. One desk, everything included.

This is a **PHP + MySQL** desk meant to run on **XAMPP**. There is no Node or Next.js. Copy the folder, start Apache and MySQL, open the installer.

Open the **landing page** at the site root. Companies **register** in a few fields. A Vellisys super admin sees the sign-up, reaches out, and onboards the company.

## Logins

**Vellisys super admin** (companies and website sign-ups)

- Email: `admin@vellisys.ug`
- Password: `vellisys-admin-2026`

**Demo company desk** (Ofagros Limited)

- Email: `accounts@ofagros.org`
- Password: `folio2026`

## What you get

- Public landing page in three sections, with pictures on every card. Super admin can replace those pictures under **Landing**, and add **Clients who trust us** names and logos
- **Have a question** form on the landing page. Super admin sees each note under **Questions**, opens the full message on its own page, and a copy is emailed to `info@vellisys.com` from the Hostinger mailbox
- Super admin **Email** tab: send a custom Vellisys letter from `info@vellisys.com`
- Super admin **Reports**: paid terms, desks due within a month, lapsed desks, and one-click renewal letters to the client
- On each company, super admin sets how many **months or years** the client has paid for. Expiry is calculated from the start date. When a desk is one month from expiry, Reports prepares a professional notice to send
- Super admin assigns each company a **Hostinger (or Titan) sending mailbox**. SMTP and POP/IMAP are prefilled. The company desk can send invoices and quotations from that address, with its logo, and cannot edit the password
- Desk **Tutorials** (also from Clients) with a tour of the whole portal and screenshots
- When a company is onboarded, `info@vellisys.com` sends a welcome letter and a short tutorial. The same mailbox is notified for sign-ups, questions, live desks, renewal letters and company mail tests
- Easy **register** form - no password to invent. Super admin sees each request, calls the company, then creates the desk
- Sign-in with a **show password** control
- **UGX or USD** on each document, converted at a rate you enter in Settings (1 USD = n UGX)
- **One document design** for the whole desk - the layout you pick in Settings prints on every invoice, quotation, receipt, expense and headed note
- **Three brand colours** (primary, accent, deep) that paint the desk and every document design
- **CSV export** on invoices, quotations, receipts, expenses, correspondence, debtors, creditors, clients, reports, and on a single document's lines
- Quotations convert to invoices; invoices take full or **part receipts**; unpaid balances stay on Debtors
- Every document can be edited after it is saved
- Print and share a clean sheet - no desk chrome, dates or page URLs around the paper
- Line items are **Item**, **Description** (paragraph), **Qty**, **Unit price**, **Total Amt**, and **VAT** as Y or N. If every line is N, VAT is left off the printed sheet
- Receipts show **RECEIVED** and **DUE**. The Receipts tab lists cleared and partially cleared payments
- Static navigation; portal background is a plain diagonal-line pattern
- **Correspondence** stays fully editable. Settings → Templates picks a document design (ledger, bills, twin copy, stripe, plus Estate cream and Lake night)
- Expenses as cards; debtors and creditors as tables with totals
- Date filters: today, this week, last week, this month, last month, plus from/to
- Company reports with a time series, expense pie and debtors aging bars
- Super admin top bar stays on screen on a phone so the menu stays in reach
- VAT 18% on taxed lines for every company
- Email sends **From** the signed-in account
- No stock module, no plan packages, no EFRIS box on documents

## Install on XAMPP (Windows)

1. Install [XAMPP](https://www.apachefriends.org/) and start **Apache** and **MySQL**.
2. Copy this entire folder to:

   `C:\xampp\htdocs\folio`

3. Open a browser:

   `http://localhost/folio/install.php`

   That creates the `folio` database, tables, Ofagros demo data, the company login and the Vellisys super admin.

4. Open `http://localhost/folio/` for the landing page, or sign in at `login.php`.

Default MySQL in XAMPP is user `root` with an empty password. If you set a password, edit `config/database.php`.

### phpMyAdmin (optional)

Create a database named `folio`, then import `sql/schema.sql`. If the `users` table is empty, still run `install.php` so the demo user and seed documents are created.

### Email (Hostinger)

Platform mail is **`info@vellisys.com`**. Super admin only. It sends and receives through Hostinger SMTP:

- SMTP `smtp.hostinger.com` port **465** SSL (STARTTLS 587 also works)
- POP `pop.hostinger.com` port **995**
- IMAP `imap.hostinger.com` port **993**

Username is the full address. Credentials live in `config/mail.php` (override with `FOLIO_SMTP_*` on the live host). Titan mailboxes for a company use `smtp.titan.email` / `pop.titan.email` / `imap.titan.email` on the same ports - pick Titan on the company page and the hosts fill in.

Company invoices do **not** leave from `info@vellisys.com`. Super admin pastes that company's Hostinger address and password on the company page. The desk user clicks Send; the letter uses the company logo and colours.

If SMTP is refused (firewall, wrong password), Vellisys still **logs the email as queued**.

Print / PDF uses the browser print dialog (Save as PDF). CSV downloads from the Export CSV buttons.

## Local PHP (without XAMPP)

```bash
# MySQL/MariaDB must be running. Then:
php install.php
php -S 127.0.0.1:43219 -t . router.php
```

Open http://127.0.0.1:43219/

You can override connection details:

```bash
export FOLIO_DB_HOST=127.0.0.1
export FOLIO_DB_NAME=folio
export FOLIO_DB_USER=root
export FOLIO_DB_PASS=
```

## Git

Commit this folder as-is. Uploaded logos live in `uploads/logos/` and are gitignored except `.gitkeep`.
