# Hosting SyncCRM on Cloudways

Cloudways is a PHP-oriented platform: no root access, no Docker and no PostgreSQL. SyncCRM still runs there with this layout:

- **App** — Node.js 22 installed with nvm in the application's SSH user home, run by PM2 on port 3000, with Apache forwarding traffic via `.htaccess`.
- **Database** — a managed PostgreSQL outside Cloudways (Neon, Supabase, DigitalOcean Managed DB, etc.). Cloudways servers reach it over SSL.
- **Files** — uploads stay on the Cloudways disk under `private_html/synccrm/uploads`.

## 1. Cloudways panel

1. Applications → **Add Application** → type **Custom App** (PHP), name `synccrm`.
2. Application → **Domain Management** → add your domain (e.g. `crm.syncworkstech.com`) and point its DNS at the server IP.
3. Application → **SSL Certificate** → Let's Encrypt for that domain.
4. Application → **Application Settings** → set **Varnish** to *Disabled* (authenticated pages must never be cached).
5. Application → **Access Details** → note the **SSH/SFTP username, password, server IP** and the application folder name.
6. Server → **Security** → allow your IP for SSH if it is restricted.

## 2. PostgreSQL

Create a Postgres 16 database (e.g. [Neon](https://neon.tech) — the free tier is enough to start; choose the Singapore region for India). Copy the connection string; it looks like
`postgresql://user:password@host/dbname?sslmode=require`.

## 3. Install (once, over SSH)

```bash
ssh <ssh-user>@<server-ip>
DATABASE_URL='postgresql://user:password@host/dbname?sslmode=require' \
APP_URL='https://crm.syncworkstech.com' \
bash <(curl -fsSL https://raw.githubusercontent.com/cerebrumtech/SyncCRM/main/deploy/cloudways.sh) install
```

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
- **Stale pages after login/logout** — Varnish is still on; disable it in Application Settings and purge.
- **App not running** — `pm2 logs synccrm` shows the reason; `pm2 restart synccrm` restarts it. The watchdog cron does the same automatically.
- **Database connection refused** — the Postgres provider must allow connections from the Cloudways server IP (Neon allows all by default; others need an allow-list).
