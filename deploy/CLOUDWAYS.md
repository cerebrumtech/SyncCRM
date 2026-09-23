# Hosting SyncCRM on Cloudways

SyncCRM is a plain PHP application: **PHP 7.4+ and MySQL, no Composer, no build step**. It runs on a standard Cloudways PHP application without changing anything server-wide.

## 1. Panel, once

1. Applications → **Add Application** → *PHP* (Custom App), name it `SyncCRM`. Note the app folder shown under **Access Details**, for example `dhrwuuxpcs`.
2. Application → **Domain Management** → add your domain and point DNS at the server, then **SSL Certificate** → Let's Encrypt.
3. Application → **Access Details** → **MySQL Access**: note the database name, username and password.

You do **not** need to change the server's PHP version. The app runs on the 7.4 that Cloudways servers commonly ship.

## 2. Install, one paste over SSH

Open the Cloudways SSH terminal (Servers → your server → Master Credentials → Launch SSH Terminal), sign in, and paste:

```bash
APP_NAME=dhrwuuxpcs \
DB_NAME='xxxxxxxx' DB_USER='xxxxxxxx' DB_PASS='xxxxxxxx' \
APP_URL='https://crm.syncworks.app' \
bash <(curl -fsSL https://raw.githubusercontent.com/cerebrumtech/SyncCRM/php-codeigniter/deploy/cloudways.sh) install
```

Add `SEED=1` in front to load the demo workspace.

The script clones the repository into the application's `public_html`, writes `.env` with a random one-time install key, creates the database tables, and prints the last two steps:

4. Application Settings → **General** → **Webroot** = `public_html/public`. The field already contains `public_html/`; add `public` on the end. Do this after the install, because the folder must exist first.
5. Application Settings → **Varnish** → *Disabled*, so signed-in pages are never cached.

Open the domain. The first visit shows **Set up your workspace**, unless you seeded demo data.

## 3. Update to the latest code

```bash
APP_NAME=dhrwuuxpcs ~/applications/dhrwuuxpcs/public_html/deploy/cloudways.sh update
```

## Does this affect the other applications on the server?

No. The script writes only inside this application's folder: the code in `public_html`, uploads in `private_html/uploads`, and the app's own MySQL database. It uses no root access and changes no nginx, Apache, Varnish, PHP-FPM or MySQL configuration, and it does not change the server's PHP version. It refuses to run if it can see several applications and none is named, and refuses to overwrite a `public_html` belonging to another app.

## No shell access?

Open `https://your-domain/install?key=<app.installKey from .env>` to create the tables from the browser. Clear `app.installKey` in `.env` afterwards.

## Troubleshooting

- **403 Forbidden from nginx** — the app is not installed yet, or the Webroot is still `public_html/`. Set it to `public_html/public`.
- **500, blank page** — check `public_html/writable/logs/`. Usually `writable/` is not writable: `chmod -R 775 writable`.
- **Styles missing** — the Webroot is wrong, or Varnish is still caching. Purge it.
- **Stale pages after login or logout** — Varnish is still enabled for this app.
- **Database connection failed** — re-check the MySQL values in `.env`; the host is `localhost`.
- **"needs PHP 7.4 or newer"** — the application is being served by an older PHP. Raise it in the panel.
