# Import Vellisys into Hostinger

You do **not** need a `.env` file. This project does not use one. Live credentials are already in PHP config:

- `config/env.php` - detects `www.vellisys.com`
- `config/database.php` - uses database `u454222977_Vell` on the live domain
- `config/mail.php` - uses `info@vellisys.com` on Hostinger SMTP

Upload the PHP files to **public_html**, then import this SQL. Forms fail online when the Hostinger database is missing tables or columns the PHP expects. Local (offline) works because the local `folio` database is complete.

## 1. Upload the latest files

Copy the whole project into Hostinger **public_html** so `index.php`, `login.php`, `register.php` and the `config/` folder sit at the site root.

## 2. Import the database

1. Open Hostinger **phpMyAdmin**.
2. Click database **`u454222977_Vell`** (do not create a database named `folio`).
3. If tables already exist, go to the database **Operations** tab or tick all tables and **Drop**. An import with `DROP TABLE IF EXISTS` also replaces them.
4. **Import** → choose **`sql/vellisys-hostinger-import.sql`** → Go.
5. Confirm `schema_meta` has `version` = `32` and `users` has columns `job_title` and `access`.

## 3. Sign in

- Super admin: `admin@vellisys.ug` / `vellisys-admin-2026`
- Demo desk: `accounts@ofagros.org` / `folio2026`

Do not run `install.php` on the live domain. It is disabled there.
