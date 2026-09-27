# Deploy Vellisys on Dokploy (Hostinger KVM 2 + Ubuntu)

This is the first-time path: **Dokploy + Docker Compose + GitHub**.  
Your current Hostinger website can keep running until you point DNS at the VPS.

You do **not** redesign pages. Same PHP app.

---

## What you need open

1. **Dokploy** in the browser (usually `http://YOUR_VPS_IP:3000`)
2. **GitHub** account that can read `FRONT-STAR-DIGITAL/VELLISYS-Web`
3. **Hostinger hPanel** (old site) — for a database export later
4. A strong password ready for MySQL (write it down)

---

## Part 1 — Confirm Dokploy is up

1. In Hostinger → VPS → note your **VPS IP**.
2. Open `http://YOUR_VPS_IP:3000` (or the URL Dokploy showed at install).
3. Sign in to Dokploy.
4. If the page does not load: in Hostinger VPS firewall / UFW allow ports **3000**, **80**, **443**, and **22**.

You are done with Part 1 when you see the Dokploy dashboard.

---

## Part 2 — Connect GitHub to Dokploy

1. In Dokploy go to **Settings** → **Git** (or **Providers**).
2. Connect **GitHub** (OAuth or a deploy key / personal access token).
3. Grant access to the org/repo **`FRONT-STAR-DIGITAL/VELLISYS-Web`**.

---

## Part 3 — Create the Vellisys project

1. Dokploy → **Create Project** → name it `vellisys`.
2. Inside the project → **Create Service** → choose **Docker Compose** (not plain Application).
3. Provider: **GitHub**.
4. Repository: `FRONT-STAR-DIGITAL/VELLISYS-Web`.
5. Branch: **`main`**.
6. Compose file path: **`docker-compose.yml`** (repo root).
7. Save.

---

## Part 4 — Environment variables (required)

Open the Compose service → **Environment** tab.  
Paste something like this (change the passwords):

```env
FOLIO_LIVE=1
FOLIO_DB_NAME=vellisys
FOLIO_DB_USER=vellisys
FOLIO_DB_PASS=ChooseAStrongPassword123!
FOLIO_DB_ROOT_PASS=ChooseAnotherStrongRootPass456!
```

Optional (only if you want to override platform mail from env):

```env
FOLIO_SMTP_HOST=smtp.hostinger.com
FOLIO_SMTP_PORT=465
FOLIO_SMTP_SECURE=ssl
FOLIO_SMTP_USER=info@vellisys.com
FOLIO_SMTP_PASS=your-mailbox-password
FOLIO_SMTP_FROM=info@vellisys.com
FOLIO_SMTP_FROM_NAME=VELLISYS
```

**Save** the environment.

> Mailboxes stay on Hostinger email. The app only *sends* through SMTP.  
> Do **not** move MX records when you move the website.

---

## Part 5 — First deploy

1. Click **Deploy**.
2. Open **Deployments** / **Logs**.
3. Wait until both services are healthy:
   - `db` (MySQL)
   - `app` (PHP + Apache)

First build can take **5–15 minutes** (downloads PHP/MySQL images).

If it fails, read the red log line. Common fixes:

| Error | Fix |
|--|--|
| `FOLIO_DB_PASS` empty | Set env vars, Save, Deploy again |
| `dokploy-network` missing | Redeploy; Dokploy normally creates this network |
| Git permission denied | Reconnect GitHub / allow the repo |

---

## Part 6 — Domain on Dokploy (before or after DNS)

1. Compose service → **Domains**.
2. Add domain: `www.vellisys.com` (and `vellisys.com` if you want both).
3. Service / container: **`app`**, port **`80`**.
4. Enable **HTTPS** (Let’s Encrypt) when Dokploy offers it.

**Important:** Let’s Encrypt only works after DNS for that domain points at this VPS IP.  
Until then, use Dokploy’s preview URL / IP to test.

---

## Part 7 — Put your real data in MySQL

The empty MySQL database is not enough. Copy the live Hostinger database once.

### A) Export from current Hostinger

1. hPanel → **phpMyAdmin**.
2. Select database **`u454222977_Vell`**.
3. **Export** → Quick → SQL → Go.  
   Save the `.sql` file on your computer.

### B) Import into the VPS MySQL (Dokploy)

Easiest path for a first deploy:

1. On your computer, upload the `.sql` into Dokploy **File** area for this service if available,  
   **or** use SSH on the VPS:

```bash
# SSH into the VPS (Hostinger gives you root password / key)
ssh root@YOUR_VPS_IP

# Find the running MySQL container name
docker ps

# Copy your dump up first (from your laptop), e.g.:
# scp vellisys.sql root@YOUR_VPS_IP:/root/vellisys.sql

# Import (replace CONTAINER and passwords)
docker exec -i CONTAINER_NAME_FOR_DB \
  mysql -uvellisys -p'ChooseAStrongPassword123!' vellisys < /root/vellisys.sql
```

If the dump was made as the old Hostinger database name, either:

- export again including only data, or  
- edit the SQL header so it uses database `vellisys`, or  
- create/import with matching name.

After import, open the app URL and try login.

---

## Part 8 — Uploads (logos / photos)

`uploads/` is not in git. Copy once from Hostinger `public_html/uploads` into Dokploy’s persistent folder:

On the VPS, that folder is under the Dokploy project files, mounted as `../files/uploads`.

Typical approach:

```bash
# From laptop: zip uploads on Hostinger, download, then:
scp -r uploads root@YOUR_VPS_IP:/tmp/uploads

# Copy into the running app volume (path may vary — check Dokploy Files)
# Often something like:
docker ps   # find app container
docker cp /tmp/uploads/. APP_CONTAINER:/var/www/html/uploads/
```

Or use Dokploy **Advanced → Mounts / Files** UI if you prefer not to use SSH.

---

## Part 9 — Test (old site still live)

Open the Dokploy preview URL (or hosts-file → VPS IP).

Check:

1. Landing page loads  
2. `/login.php` works  
3. Super admin can sign in  
4. One company desk opens  
5. Create a quick document  
6. Send a test email (still via Hostinger SMTP)

If these work, the VPS app is ready. Customers can stay on the old Hostinger site until you cut DNS.

---

## Part 10 — Go live (short maintenance window)

When you are happy with the VPS copy:

1. Lower DNS TTL the day before (if you can).
2. Final MySQL export from Hostinger → import again on VPS (so data is fresh).
3. Point **A record** for `www.vellisys.com` (and apex) to **VPS IP**.
4. Leave **MX** records on Hostinger mail (do not change mail DNS).
5. In Dokploy, finish SSL for `www.vellisys.com`.
6. Test login on the real domain.
7. Keep old Hostinger web hosting for a few days as rollback.

Planned user impact: about **30–60 minutes** if prep is done.

---

## Day-2 updates (this is the easy part)

After go-live, every code update is:

1. Merge/push to `main` on GitHub  
2. Dokploy **Deploy** (or enable Auto Deploy on push)

Database and `uploads/` stay on the VPS volumes — they are not wiped by `git pull` / redeploy.

---

## Rollback

Point DNS A records back to the old Hostinger web IP.  
Users return to the previous site (as long as you have not deleted that hosting).

---

## Quick mental model

| Piece | Where it lives |
|--|--|
| PHP code | GitHub → Dokploy builds Docker image |
| MySQL data | VPS volume `../files/mysql` |
| Uploaded files | VPS volume `../files/uploads` |
| Email inboxes | Still Hostinger / Titan mail |
| Domain HTTPS | Dokploy Traefik + Let’s Encrypt |
