# Hosting SyncCRM on Cloudways

SyncCRM is a standard PHP 8.1+ / MySQL application (CodeIgniter 4.6), so it runs on a Cloudways **PHP** application with no extra services.

## 1. Cloudways panel (once)

1. Applications → **Add Application** → *PHP* (Custom App) → name it `SyncCRM`. Note the app folder name shown under **Access Details** (e.g. `dhrwuuxpcs`).
2. Server → **Settings & Packages** → PHP version **8.1 or newer** (most Cloudways servers already are; it is a server-wide setting, so leave it alone if it is already 8.1+).
3. Application → **Domain Management** → add your domain (e.g. `crm.syncworks.app`), point DNS at the server, then **SSL Certificate** → Let's Encrypt.
4. Application → **Access Details** → **MySQL Access**: note the database name, username and password.

## 2. Install (one paste over SSH)

Use the server's master credentials (or the application's own SSH user) and paste, filling in your values:

```bash
APP_NAME=dhrwuuxpcs \
DB_NAME='xxxxxxxx' DB_USER='xxxxxxxx' DB_PASS='xxxxxxxx' \
APP_URL='https://crm.syncworks.app' \
bash <(curl -fsSL https://raw.githubusercontent.com/cerebrumtech/SyncCRM/php-codeigniter/deploy/cloudways.sh) install
```

Add `SEED=1` in front to load the demo workspace (owner@syncworkstech.com / password123).

The script clones the repository into the application's `public_html`, runs Composer, writes `.env` (with a random encryption key and a one-time install key), creates the database tables and prints the two panel settings that finish the job:

5. Application → **Application Settings** → General → **Webroot** = `public_html/public` (the field already contains `public_html/`; add `public` to the end). Do this *after* the install, because the folder must exist first.
6. Application → **Application Settings** → **Varnish** = *Disabled* (or add a URL exclusion for `/` on the Varnish tab).

Open the domain: the first visit shows **Set up your workspace** (unless you seeded demo data).

Uploaded files are stored in `private_html/uploads` (outside the web root). Everything the script does stays inside this one application's folder; other applications, PHP, MySQL, Apache/nginx and Varnish settings for other apps are untouched.

## 3. Update to the latest code

```bash
APP_NAME=dhrwuuxpcs ~/applications/dhrwuuxpcs/public_html/deploy/cloudways.sh update
```

(Or use Cloudways' **Deployment via Git** on the app to pull the repository, then run `php spark migrate` in `public_html`.)

## No shell access?

The one-time web installer runs the database setup from the browser: open `https://your-domain/install?key=<app.installKey from .env>`. Remove `app.installKey` from `.env` afterwards.

## Troubleshooting

- **Blank page / 500** — check `public_html/writable/logs/`. Most often PHP is older than 8.1 or `writable/` is not writable (`chmod -R 775 writable`).
- **403 Forbidden from nginx** — either the app is not installed yet, or the Webroot is still `public_html/`; set it to `public_html/public`.
- **Styles missing** — assets are served from `public/assets`; purge Varnish or disable it for this app.
- **Stale pages after login/logout** — Varnish is still on for this app.
- **Database connection failed** — re-check the MySQL credentials in `.env` (Access Details → MySQL Access); the host is `localhost`.
