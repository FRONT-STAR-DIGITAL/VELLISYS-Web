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
FOLIO_REDIS_PASS=ChooseAStrongRedisPassword789!
```

`FOLIO_REDIS_PASS` protects the Redis container on the Docker network (short app cache).  
Logins stay in MySQL — Redis is optional cache only. If Redis is unhealthy, the desk still works with file cache.

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

Optional (recommended on VPS — keeps logins stable across redeploys):

```env
FOLIO_AUTH_SECRET=paste-a-long-random-string-here
```

Logins are stored in MySQL (`php_sessions`) plus a signed cookie. There is no idle logout — only **Sign out** clears a login.

**Save** the environment.

> Mailboxes stay on Hostinger email. The app only *sends* through SMTP.  
> Do **not** move MX records when you move the website.

---

## Part 5 — First deploy

1. Click **Deploy**.
2. Open **Deployments** / **Logs**.
3. Wait until these services are healthy:
   - `db` (MySQL)
   - `redis` (cache)
   - `app` (PHP + Apache)

First build can take **5–15 minutes** (downloads PHP/MySQL/Redis images).

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

If import fails with `Unknown collation: 'utf8mb4_uca1400_ai_ci'`, the dump is from Hostinger MariaDB and the DB image must be MariaDB (this repo’s compose uses `mariadb:11.4`). Quick fix without rebuilding:

```bash
sed 's/utf8mb4_uca1400_ai_ci/utf8mb4_unicode_ci/g' /root/vellisys.sql > /root/vellisys-fixed.sql
docker exec -i YOUR_DB_CONTAINER mysql -uvellisys -p'YOUR_DB_PASS' vellisys < /root/vellisys-fixed.sql
```

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

## Speed (reload feel)

After go-live, desk reloads are usually a bit slower than classic Hostinger web hosting because traffic goes **Traefik → Docker → Apache/PHP → MariaDB**. We keep quality (live data, correct schema) and cut waste:

- Schema “ensure” work runs once, then a cache stamp skips it on later requests
- Production PHP OPcache is on in the Docker image
- **Redis** holds short-lived app cache (`folio_remember`) so common lookups hit RAM instead of MySQL/files
- `uploads/` and `storage/cache` persist on VPS volumes

If a page still feels heavy, it is usually that page’s queries (reports/charts), not DNS. Redeploy after pulling `main` to pick up speed fixes.

---

## Cloudflare Free (in front of the VPS)

Cloudflare Free sits in front of Dokploy. It caches static files, absorbs junk traffic, and gives free DDoS protection. **No Cloudflare bill** if you stay on Free and do not buy Workers/Images add-ons.

### A) Add the domain (Cloudflare dashboard)

1. Account home → **Add a domain** (middle card).
2. Type: `vellisys.com` (apex — Cloudflare will cover `www` too).
3. Continue → choose **Free** plan → Continue.
4. Cloudflare scans DNS. Keep existing **MX** records for Hostinger/Titan mail (do not delete mail rows).
5. Finish until Cloudflare shows **two nameservers** (e.g. `ada.ns.cloudflare.com` and `bob.ns.cloudflare.com`).

### B) Point the domain at Cloudflare

1. Open the place that currently holds DNS for `vellisys.com` (Hostinger domain / registrar).
2. Change **nameservers** to the two Cloudflare nameservers.
3. Wait for Cloudflare status **Active** (can be minutes to a few hours).

### C) Proxy + SSL (after Active)

1. Cloudflare → your domain → **DNS**.
2. For `vellisys.com` and `www`, set **A** (or CNAME) to your **VPS IP** (Dokploy).
3. Turn the cloud **orange** (Proxied) for those web records.
4. Leave **MX** as DNS only (grey cloud) — mail stays on Hostinger.
5. **SSL/TLS** → Overview → encryption mode:
   - Prefer **Full (strict)** once Dokploy Let’s Encrypt is working for `www.vellisys.com`.
   - Use **Full** temporarily if the origin cert is not ready yet.
   - Avoid **Flexible** (can break logins / mixed HTTPS).

### D) Caching (optional, Free)

1. **Caching** → Configuration → Caching Level: **Standard**.
2. Browser Cache TTL can stay default.
3. Do **not** “Cache Everything” for the whole site — PHP desk pages must stay dynamic. Static assets already send long `Cache-Control` from Apache.

Ignore sidebar **Storage & databases** / Workers for this setup — Redis runs inside Dokploy on your VPS, not in Cloudflare.

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
