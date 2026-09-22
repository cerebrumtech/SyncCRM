# Hosting SyncCRM on Cloudways

Cloudways is a PHP-oriented platform: no root access, no Docker and no PostgreSQL. SyncCRM still runs there with this layout:

- **App** — Node.js 22 installed with nvm in the application's SSH user home, run by PM2 on the first free port from 3000 up, with Apache forwarding traffic via `.htaccess`. The build's static assets (`_next/static`, `public/`) are copied into `public_html` because Cloudways' nginx serves files that exist there directly and only passes the rest to Apache.
- **Database** — a managed PostgreSQL outside Cloudways (Neon, Supabase, DigitalOcean Managed DB, etc.). Cloudways servers reach it over SSL.
- **Files** — uploads stay on the Cloudways disk under `private_html/synccrm/uploads`.

## 1. Cloudways panel

1. Applications → **Add Application** → type **Custom App** (PHP), name `synccrm`.
2. Application → **Domain Management** → add your domain (e.g. `crm.syncworkstech.com`) and point its DNS at the server IP.
3. Application → **SSL Certificate** → Let's Encrypt for that domain.
4. Application → **Application Settings** → **General** tab → set **Varnish** to *Disabled* (authenticated pages must never be cached). If that tab has no Varnish switch, open the **Varnish** tab and add an exclusion: Type *URL*, Method *Contains*, Value `/`.
5. Application → **Access Details** → note the **SSH/SFTP username, password, server IP** and the application folder name.
6. Server → **Security** → allow your IP for SSH if it is restricted.

## 2. PostgreSQL

Create a Postgres 16 database (e.g. [Neon](https://neon.tech) — the free tier is enough to start; choose the Singapore region for India). Copy the connection string; it looks like
`postgresql://user:password@host/dbname?sslmode=require`.

## Does this affect other applications on the server?

No. The script only writes inside the SyncCRM application folder (`private_html/synccrm` for the code and uploads, one `.htaccess` in its `public_html`) and inside the SSH user's home (`~/.nvm`, `~/.pm2`). It never touches other applications' folders, PHP, MySQL, Apache/nginx config or Varnish for other apps. Safeguards: it refuses to run when the SSH user can see several applications and none is chosen, and it refuses to install into a `public_html` that already has files.

Resources: the app itself uses ~300–500 MB RAM. The one-time `next build` needs ~1.5 GB free for a few minutes — on a 1 GB Cloudways server run the install at a quiet time or resize to 2 GB first. Port 3000 is only used locally on the server (set `PORT=3001` if something else already listens there).

## 3. Install (once, over SSH)

```bash
ssh <ssh-user>@<server-ip>
DATABASE_URL='postgresql://user:password@host/dbname?sslmode=require' \
APP_URL='https://crm.syncworkstech.com' \
bash <(curl -fsSL https://raw.githubusercontent.com/cerebrumtech/SyncCRM/main/deploy/cloudways.sh) install
```

Use the **application's own SSH credentials** (Application → Access Details → Application Credentials) so the script can only see that one app. If you use the server's master user instead, add `APP_NAME=<folder name>` (the folder shown under Access Details, e.g. `APP_NAME=abcdefghij`) in front of the command.

The script installs Node + pnpm + PM2 (user-level, no root), clones the repo into `private_html/synccrm`, writes `.env`, applies the database migrations, builds, starts the app and writes the Apache proxy `.htaccess` into `public_html`.

Then add the watchdog cron in the panel (Application → **Cron Job Management** → Advanced), running every minute:

```
* * * * *  /home/<ssh-user>/applications/<app>/private_html/synccrm/deploy/cloudways-watchdog.sh
```

It restarts the app after reboots or crashes. Optional demo data: `cd ~/applications/<app>/private_html/synccrm && pnpm db:seed`.

## 4. Update to the latest code

```bash
ssh <ssh-user>@<server-ip>
~/applications/<app>/private_html/synccrm/deploy/cloudways.sh update
```

## Troubleshooting

- **500 / "Proxy Error" from Apache** — `mod_proxy` is not enabled on that server. Ask Cloudways support to enable `mod_proxy` and `mod_proxy_http` for the application (they do this on request), then reload the page.
- **Stale pages after login/logout** — Varnish is still on; disable it in Application Settings → General (or add a URL exclusion for `/` on the Varnish tab) and purge.
- **The page only shows the word "SyncCRM" or a Cloudways placeholder** — a static `index.html`/`index.php` is sitting in `public_html`, and nginx serves it before Apache's proxy rule runs. Remove it (`rm ~/applications/<app>/public_html/index.html`) or rerun the installer's `update`, which does this for you.
- **App not running** — `pm2 logs synccrm` shows the reason; `pm2 restart synccrm` restarts it. The watchdog cron does the same automatically.
- **Database connection refused** — the Postgres provider must allow connections from the Cloudways server IP (Neon allows all by default; others need an allow-list).
