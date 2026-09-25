# Vellisys

Branded books for companies anywhere in the world - quotations, invoices, receipts, expenses and headed letters in the company's own logo, colour and currency. One desk, everything included. Built for East Africa and used across Africa and worldwide.

This is a **PHP + MySQL** desk meant to run on **XAMPP**. There is no Node or Next.js. Copy the folder, start Apache and MySQL, open the installer.

Open the **landing page** at the site root. Companies **register**. A Vellisys super admin sees the request, reaches out, and onboards the company.

## Logins

**Vellisys super admin** (companies and website sign-ups)

- Email: `admin@vellisys.ug`
- Password: `vellisys-admin-2026`

**Demo company desk** (Ofagros Limited)

- Company admin: `accounts@ofagros.org` / `folio2026`
- Desk: `desk@ofagros.org` / `folio2026`
- Sales: `sales@ofagros.org` / `folio2026`

**Field sales agents** (same login page; no desk or onboarding rights)

- Demo agent: `agent@vellisys.ug` / `agent-demo-2026`
- Super admin sets **daily / weekly / monthly** goals (defaults: 10 leads + 2 sales a day, 10 sales a week, 30 a month), sees per-agent daily progress on **Sales**, opens charts by period, messages agents, CRUD businesses, and onboards interested leads. Deleting an agent requires typing their email.
- Agents clock in daily with a city, see daily goal bars (over-goal still shown), log businesses (status first: Interested / Follow up / Rejected), and open Performance for daily / weekly / monthly charts + chat
- Client desk passwords are stored under admin **Passwords** (copy when helping a client sign in)

## What you get

- Public landing page in three sections, with pictures on every card. Super admin can replace those pictures under **Landing**, and add **Clients who trust us** names and logos
- **Clients who trust us** and **What clients say** keep auto-scrolling. You can drag either strip; after you stop, auto-scroll continues from there. Super admin edits them under Landing
- Landing page on a phone stays in the screen - no sideways page scroll
- **What you get to manage** on the landing page: quotations, invoices, receipts, expenses, delivery notes, custom documents, debtors, creditors, headed letters, email, 17 document layouts (including a counter slip, a pad chit, page borders and an 80mm thermal roll), branding, and branches on Business and Pro
- A navy **top bar** above the header. Super admin edits the scrolling statements under **Landing**. Lines are separated by a blue |
- Super admin **onboarding**: tick only the documents that company needs (quotations, invoices, receipts, delivery notes, headed letters, custom). Custom documents are a form with fields and/or a body, not a letter. **Send login credentials** emails the desk email and a temporary password from `info@vellisys.com` and asks the client to change it after they sign in
- After training, super admin can **reset selected desk data** (documents, stock, clients, mail log, activities, planner, P&L) so the same login is as good as new for official books. A Vellisys **backup file** can be restored on that company page
- Super admin **payment receipt**: on create company, or after recording a paid term, send a thanks-and-welcome receipt from `info@vellisys.com` with the amount received and the subscribed period
- Super admin **onboarding checklist** on each company: package paid, receipt email, admin credentials, first sign-in, branding saved, mailbox, welcome email, desk live, and client confirms login
- **Have a question** form on the landing page. Super admin sees each note under **Questions**, opens the full message on its own page, and a copy is emailed to `info@vellisys.com`. The person who sent the question also gets a confirmation from that mailbox
- Super admin **Email** tab: send a custom Vellisys letter from `info@vellisys.com`, and **open any letter** that left that mailbox
- Super admin **Settings**: stock add-on amounts, the default temporary desk password, whether public register / demo / checkout is open, and the **currency for super-admin figures**. A Settings button sits in the admin header and in the rail. Edit, reset password, suspend and delete desk logins on each company page.
- Super admin **Dashboard**: snapshot of reload speed (fast / healthy / slow), who is on a desk now, people onboard, branches, and money taken in
- Super admin **Finances**: package payments and paid terms over time, renewals and what each company pays. This is Vellisys income, not a company desk's own books
- Super admin **System**: health, users, branches, activity in a date range, last sign-ins, who is online, and which companies use the desk most
- Super admin **Locations**: country, Uganda region, city/district, and where desks are performing well. Set on each company; the company desk cannot see it
- Super admin **Reports**: onboarding and expiry charts, sign-up funnel, site visits, paid-term renewal letters. Company desk collections and outstanding are not listed here
- On each company, super admin sets how many **weeks, months or years** the client has paid for (decimals such as 1.5 are allowed), the **fee** and **amount paid**, and expiry is calculated from the start date. When a desk is one month from expiry, Reports prepares a professional notice to send
- Company desk **top bar** shows live date and time in the company’s time zone (Settings → Company), how much of the paid term is left (days and months), and the expiry date
- Company reports with a time series, expense pie, debtors aging, collections vs outstanding, quote conversion, top clients, documents issued over time, and a tax payable table (items, receipts, output less input)
- Super admin assigns each company a **Hostinger, Titan or Gmail sending mailbox**. SMTP and POP/IMAP are prefilled. For Gmail, use an App Password (not the Gmail sign-in password). The company desk sends quotations, invoices, receipts, headed letters, debtor reminders, notes to creditors, and custom mail from that address, with its logo on a white band, and cannot edit the password. Use the eye icon to show or hide the mailbox password while you type.
- Desk **Email** tab for custom letters; **Remind** on Debtors and **Message** on Creditors use the same mailbox
- Super admin **Create company**: type a desk in by hand (login, stationery, documents, paid term, mailbox) without a website sign-up. Tick **Send a payment receipt** to email thanks, the amount paid, and the subscribed period from `info@vellisys.com`
- Super admin **Landing**: edit top-bar lines, **packages**, **testimonials**, card photos and trust logos
- **Pay** after company details - continue to Pesapal in the same tab so mobile money and cards actually accept a number. When Pesapal confirms payment the desk is created, `info@vellisys.com` is notified, and the buyer gets a link to set their admin email and password. Companies can also **register without paying** or **book a demo**; super admin sees those on Sign-ups and onboards them. First sign-in emails the client a welcome from `info@vellisys.com` and shows a one-time welcome pop-up on the desk.
- Desk **Tutorials** (also from Clients) with a tour of the whole portal. Tap a screenshot to enlarge it.
- When a company is onboarded, `info@vellisys.com` sends a welcome letter and a short tutorial. The same mailbox confirms questions and registrations, notifies the admin of those events, marks desks live, and sends renewal reminders
- Self-onboard after a successful package payment: choose admin name, sign-in email and password (twice), then sign in. Super admin still sees every step on the company page. `info@vellisys.com` is copied on payment, credentials, first sign-in and branding saved
- Header on the public site stays put when you scroll up, and tucks away when you scroll down. The WhatsApp button stays a round green mark in the corner
- Sign-in with a **show password** control
- **Any currency** on the desk - the company enters UGX, KES, EUR, USD or another three-letter code in Settings. Documents can also be in USD; the rate is 1 USD = n of the home currency
- **One document design** for the whole desk - the layout you pick in Settings prints on every invoice, quotation, receipt, expense and headed note. **Counter booklet** and **Pad chit** are fill-in slips (no item table) that still print whatever To fields the company uses
- **Two brand colours** (primary and accent) that paint the desk and every document design. Deep is derived from primary.
- **CSV export** on invoices, quotations, receipts, expenses, letters, debtors, creditors, clients, reports, and on a single document's lines
- Clients have **Active / Inactive** status and a **Delete** action. Inactive names stay off new documents; deleted names leave the list while their issued documents stay in the books. Document tables use the same delete (void) icon
- Quotations convert to invoices; invoices take full or **part receipts**; unpaid balances stay on Debtors
- Every document can be edited after it is saved
- Print and share a clean sheet - no desk chrome, dates or page URLs around the paper. On a phone, print uses a blank sheet so Safari does not stamp the desk link at the bottom
- Logo and signature stay in MySQL as well as files. In Settings you can draw a signature or **upload a small image** (under 400 KB). On a quotation, invoice, receipt or letter, tick **Add signature** to stamp that mark on Authorized by and company sign-off lines
- Phone menu sits flush under the header and hugs the links, with Accounts directly under the last item
- Line items are **Item**, **Description** (paragraph), **Qty**, **Unit price**, **Total Amt**, and **tax** as Y or N. If every line is N, tax is left off the printed sheet
- Receipts show **RECEIVED** and **DUE**. The Receipts tab lists cleared and partially cleared payments
- Static navigation; desk portals use the same pale wash as the public site, a faint blue V, and soft card shadows
- **Letters** print on the same document design as invoices. Download a Microsoft Word letterhead from Letters. Letter and email bodies use a formatting toolbar (bold, italic, underline, lists, alignment). Document dates can be typed or picked, including past dates for backdating
- Expenses as cards; debtors and creditors as tables with totals
- Date filters: today, this week, last week, this month, last month, plus from/to
- Company reports with a time series, expense pie, debtors aging, collections vs outstanding, quote conversion, top clients billed, documents issued over time, and tax payable
- Super admin top bar stays on screen on a phone so the menu stays in reach
- Company desk and every document template scale to fit a phone; line editors stack on a phone; the document table preview stays full width and scrolls sideways
- **Activities** on every package: a log of major desk events (issued sheets, payments, mail, clients, settings). Open it from the rail or from Desk
- **Planner** (Business and Pro) includes a Tasks tab for goals to hit, with due dates in the notification bell
- **Branches** (Vellisys Business and Pro): Head office is the company address; Business allows up to 2 branches, Pro up to 3; several people can share a branch; Vellisys sets how many users the desk has (Start 2, Business 3, Pro 4)
- **Push notifications** on the installed app: allow them in Settings → Notifications. The same prompt appears after you install the app. Desk alerts then pop up on the device, including notices already in the bell.
- Each company sets its **own tax name and rate** in Settings (VAT 18%, GST 16%, SST 8%…). Tick whether new products and document lines should start with tax on; you can still mark Y or N on each item. Older sheets keep the rate they were saved with
- **Stock management** (switched on per desk by Vellisys, any package): Stock (products and services, counts, purchases) and Sale. Open/close day, Day reports and filters live on the **Desk dashboard**. Quick receipts (amount received) count as sales of services on Day, the same way expenses already land there. Opening cash is applied to expenses first and noted on Day. Services have a selling price only — no opening quantity or buying price — and they are left out of Stock at sell. Quotes pick items from stock. Daily backup and restore live in Settings. The Ofagros demo (`accounts@ofagros.org`) has stock on
- Desk **Settings → Bring in books**: download Excel templates for clients, documents, receipts, sales (and stock when that add-on is on). Fill the sheet from old books and upload. Vellisys **adds** the rows to the current desk (matching client names are reused; duplicate document numbers are skipped). This is not a backup restore and does not wipe issued sheets
- Email sends **From** the signed-in account

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
- pay for a package (thanks, amount, paid term, and the link to set a password)
- set their admin login after payment
- sign in for the first time
- save company branding
- are onboarded by an admin (welcome + tutorials)
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

**A copy of every outbound Vellisys letter** (from `info@vellisys.com` to a client) is placed in the `info@` Inbox and, if set, sent to **Your Gmail** in super-admin Settings. Company-desk mail (quotations, invoices, reminders) is **not** copied through `info@` - that mix of another company's letter sent from info@ to info@ is what Hostinger started filing as Spam.

Company mail uses the company's logo, colours and contact details, with **Sent from Vellisys system** under the letter. Reminders and custom mail on the desk leave from the company mailbox.

Print uses the browser print dialog. **Download** saves the branded document immediately. CSV exports the full document (header, client, lines and totals). Dates print as 20 Jun 2026. On a phone, document actions sit in two columns. Settings is in the top bar.

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

When someone installs the web app, the icon opens **login**, not the landing page. After they sign in, they go to the desk as usual. A browser tab on the domain is unchanged. Waiting desk notices put a number on the installed app icon (iPhone, many Androids, Windows), like Gmail. If the network drops, the app shows a Vellisys offline page with **Try again** instead of the browser’s default error screen (Chrome dinosaur, Safari cannot connect). They must have opened the site once while online so the page can be stored.

## Hostinger (`www.vellisys.com`)

There is **no `.env` file**. Do not add one. On `www.vellisys.com` the app already uses the Hostinger database and mailbox from `config/database.php` and `config/mail.php`.

1. In hPanel, point **www.vellisys.com** (and the apex `vellisys.com`) at this hosting. Turn on SSL. The included `.htaccess` sends apex and HTTP to `https://www.vellisys.com`.
2. Set PHP to **8.2 or 8.3**.
3. Upload this project into **public_html** (document root of `www.vellisys.com`). Keep `index.php` at the root, not inside a subfolder.
4. In phpMyAdmin, select **`u454222977_Vell`** and import **`sql/vellisys-hostinger-import.sql`**. Full steps: `sql/IMPORT-HOSTINGER.md`. Do not run `install.php` on the live domain (it is disabled there).
5. After import, sign in at `https://www.vellisys.com/login.php`.

If Register, Login and Checkout submit to a blank page online, the live database is behind the PHP. Re-import that SQL dump. Local XAMPP still uses `folio` / `root`.

## Git

Commit this folder as-is. Landing photos, logos, PWA icons, tutorial screenshots, and files under `uploads/` (company logos, landing card photos, trust logos) stay in git so a push still has the pictures.
