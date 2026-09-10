# Import Vellisys into Hostinger

Use this when the live database is empty or you are replacing it.

1. Open Hostinger **phpMyAdmin**.
2. Select database **`u454222977_Vell`**.
3. If this is a re-import, empty or drop the existing tables in that database first.
4. Import **`vellisys-hostinger-import.sql`**.
5. Do not create a second database named `folio` on Hostinger. The dump has no `CREATE DATABASE` statement; it loads into whichever database you selected.

The PHP app connects as user **`u454222977_Vell`** when it sees the live domain. Credentials are in `config/database.php`.
