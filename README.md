# Vellisys

Branded books for companies anywhere in the world - quotations, invoices, receipts, expenses and headed correspondence in the company's own logo, colour and currency. One desk, everything included. The branded alternative to QuickBooks and other finance software, built for East Africa and used across Africa and worldwide.

This is a **PHP + MySQL** desk meant to run on **XAMPP**. There is no Node or Next.js. Copy the folder, start Apache and MySQL, open the installer.

Open the **landing page** at the site root. Companies **register**. A Vellisys super admin sees the request, reaches out, and onboards the company.

## Logins

**Vellisys super admin** (companies and website sign-ups)

- Email: `admin@vellisys.ug`
- Password: `vellisys-admin-2026`

**Demo company desk** (Ofagros Limited)

- Email: `accounts@ofagros.org`
- Password: `folio2026`

## What you get

- Public landing page in three sections, with pictures on every card. Super admin can replace those pictures under **Landing**, and add **Clients who trust us** names and logos
- **Clients who trust us** and **What clients say** keep auto-scrolling. You can drag either strip; after you stop, auto-scroll continues from there. Super admin edits them under Landing
- Landing page on a phone stays in the screen - no sideways page scroll
- **What you get to manage** on the landing page: quotations, invoices, receipts, expenses, delivery notes, custom documents, debtors, creditors, headed letters, email, 10+ templates, branding
- A navy **top bar** above the header. Super admin edits the scrolling statements under **Landing**. Lines are separated by a blue |
- Super admin **onboarding**: tick only the documents that company needs (quotations, invoices, receipts, delivery notes, headed letters, custom). Custom documents are a form with fields and/or a body, not a letter
- Super admin **payment receipt**: after recording a paid term, send a receipt from `info@vellisys.com` with start date, expiry, currency, amount received, thanks, and a wait for onboarding credentials when the desk is not live yet
- **Have a question** form on the landing page. Super admin sees each note under **Questions**, opens the full message on its own page, and a copy is emailed to `info@vellisys.com`. The person who sent the question also gets a confirmation from that mailbox
- Super admin **Email** tab: send a custom Vellisys letter from `info@vellisys.com`
- Super admin **Reports**: fees collected and balances per client, unused term value, desk collections vs outstanding, onboarding and expiry charts, sign-up funnel, time series of platform books, plus paid terms, desks due within a month, lapsed desks, and one-click renewal letters
- On each company, super admin sets how many **months or years** the client has paid for, the **fee** and **amount paid**, and expiry is calculated from the start date. When a desk is one month from expiry, Reports prepares a professional notice to send
- Company desk **top bar** shows live date and time, how much of the paid term is left (days and months), and the expiry date
- Company reports with a time series, expense pie, debtors aging, collections vs outstanding, quote conversion, top clients, and documents issued over time
- Super admin assigns each company a **Hostinger (or Titan) sending mailbox**. SMTP and POP/IMAP are prefilled. The company desk sends quotations, invoices, receipts, headed letters, debtor reminders, notes to creditors, and custom mail from that address, with its logo on a white band, and cannot edit the password
- Desk **Email** tab for custom letters; **Remind** on Debtors and **Message** on Creditors use the same mailbox
- Super admin **Create company**: type a desk in by hand (login, stationery, documents, paid term, mailbox) without a website sign-up
- Super admin **Landing**: edit top-bar lines, **packages**, **testimonials**, card photos and trust logos
- **Pay** after company details, on the checkout page - the payment form stays on Vellisys (hosted checkout in the page: mobile money, cards, bank, wallet). The buyer is emailed that payment awaits, plus an unpaid invoice. `info@vellisys.com` is copied on those letters. Incomplete checkouts and failed payments are kept and emailed to that mailbox. Register without paying if you want a call first
- Desk **Tutorials** (also from Clients) with a tour of the whole portal and screenshots
- When a company is onboarded, `info@vellisys.com` sends a welcome letter and a short tutorial. The same mailbox confirms questions and registrations, notifies the admin of those events, marks desks live, and sends renewal reminders
- Easy **register** form - no password to invent. Super admin sees each request, the person who registered gets a confirmation from `info@vellisys.com`, then an admin calls the company and creates the desk
- Header on the public site stays put when you scroll up, and tucks away when you scroll down. The WhatsApp button stays a round green mark in the corner
- Sign-in with a **show password** control
- **Any currency** on the desk - the company enters UGX, KES, EUR, USD or another three-letter code in Settings. Documents can also be in USD; the rate is 1 USD = n of the home currency
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
- Company reports with a time series, expense pie, debtors aging, collections vs outstanding, quote conversion, top clients billed, and documents issued over time
- Super admin top bar stays on screen on a phone so the menu stays in reach
- Company desk and every document template scale to fit a phone; line items stack so they stay easy to fill
- VAT 18% on taxed lines for every company
- Email sends **From** the signed-in account
- No stock module, no EFRIS box on documents

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

Two mailboxes, never mixed.

**Vellisys letters** leave from **`info@vellisys.com`**. Super admin only. Clients receive from that address when they:

- submit a question
- register for a desk
- start or fail a package checkout
- pay for a term (payment receipt: start, expiry, currency, amount, thanks, wait for credentials)
- are onboarded (welcome + tutorials)
- are marked live
- are due a renewal reminder
- are sent a custom letter from the admin Email tab

Hostinger SMTP:

- SMTP `smtp.hostinger.com` port **465** SSL (STARTTLS 587 also works)
- POP `pop.hostinger.com` port **995**
- IMAP `imap.hostinger.com` port **993**

Username is the full address. Credentials live in `config/mail.php` (override with `FOLIO_SMTP_*` on the live host). Letters use navy `#08143A`, blue `#1E4EFF`, white, and black text. The Vellisys logo sits on a white band.

**Company letters** do **not** leave from `info@vellisys.com`. Super admin pastes that company's Hostinger (or Titan) address and password on the company page. The desk then sends quotations, invoices, receipts, headed letters, debtor reminders, notes to creditors, and custom emails from that mailbox, with the company logo on white. Titan hosts are `smtp.titan.email` / `pop.titan.email` / `imap.titan.email` on the same ports - pick Titan on the company page and the hosts fill in.

If SMTP is refused (firewall, wrong password), Vellisys still **logs the email as queued**. Retry queued on the admin Email tab only retries Vellisys letters, not company mail.

**A copy of every outbound letter** also arrives at **`info@vellisys.com`**, including company-desk mail (quotations, invoices, receipts, letters, reminders). The copy is marked for Vellisys and Reply-To is the client, so the team can answer from that inbox. Letters that were already addressed to `info@vellisys.com` are not copied again.

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

## Web app

The public website still opens on the **landing page**. Sign in at `login.php`. That page has the **Install the Vellisys app** button and the iPhone / computer instructions.

When someone installs the web app, the icon opens **login**, not the landing page. After they sign in, they go to the desk as usual. A browser tab on the domain is unchanged.

## Hostinger (`www.vellisys.com`)

There is **no `.env` file**. Do not add one. On `www.vellisys.com` the app already uses the Hostinger database and mailbox from `config/database.php` and `config/mail.php`.

1. In hPanel, point **www.vellisys.com** (and the apex `vellisys.com`) at this hosting. Turn on SSL. The included `.htaccess` sends apex and HTTP to `https://www.vellisys.com`.
2. Set PHP to **8.2 or 8.3**.
3. Upload this project into **public_html** (document root of `www.vellisys.com`). Keep `index.php` at the root, not inside a subfolder.
4. In phpMyAdmin, select **`u454222977_Vell`** and import **`sql/vellisys-hostinger-import.sql`**. Full steps: `sql/IMPORT-HOSTINGER.md`. Do not run `install.php` on the live domain (it is disabled there).
5. After import, sign in at `https://www.vellisys.com/login.php`.

If Register, Login and Checkout submit to a blank page online, the live database is behind the PHP. Re-import that SQL dump. Local XAMPP still uses `folio` / `root`.

## Git

Commit this folder as-is. Uploaded logos live in `uploads/logos/` and are gitignored except `.gitkeep`.
