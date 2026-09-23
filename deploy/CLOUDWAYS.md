# Running SyncCRM in production

Everything needed to understand, deploy and troubleshoot SyncCRM on Cloudways. If you are
picking this up cold, read "How it actually runs" first — most surprises come from the
server, not the code.

## Live deployment

| | |
|---|---|
| URL | https://crm.syncworks.app |
| Host | Cloudways (shared server, ~31 applications on it) |
| Application folder | `dhrwuuxpcs` |
| Full path | `/home/master/applications/dhrwuuxpcs/public_html` |
| Webroot setting | `public_html/public` |
| Varnish | Disabled |
| PHP | 7.4.33 (server-wide) |
| Database | MySQL `dhrwuuxpcs` on `localhost` |
| Uploads | `/home/master/applications/dhrwuuxpcs/private_html/uploads` |
| Branch deployed | `php-codeigniter` (identical to `main`) |

Other applications share this server, including production systems. Nothing in this
directory touches them — see "Blast radius" below.

## How it actually runs

Four facts about this environment explain nearly every problem encountered so far.

**1. nginx serves the site, not Apache.** Cloudways puts nginx in front of PHP-FPM with no
Apache in the request path. `.htaccess` files are never read. Rewrites come from nginx's
`try_files $uri $uri/ /index.php?$query_string`, which is why the app needs no rewrite
config of its own — but also why any approach that relies on `.htaccess` silently fails.

**2. The Webroot must point at `public/`.** Cloudways' Webroot field takes a path relative
to the application folder, so it reads `public_html/public`, not `public`. Point it at
`public_html` and nginx serves the repository root, where there is no `index.php` — that is
the 403 and the 404.

**3. PHP 7.4 is server-wide and not per-application.** Cloudways support confirmed a single
PHP version for the whole server, and the other 30 applications need 7.4. That constraint is
why this app has no framework: every maintained PHP framework has dropped 7.4. The code is
written to 7.4 syntax and verified against it — see "Staying on PHP 7.4".

**4. PHP-FPM runs as a different user than your SSH login.** You log in as
`master_<something>`; the site is served as the application user. Any file the web request
must read has to be readable by *both*. This bit `.env` once already.

## Deploying

### Automatic (normal case)

`deploy/auto-update.sh` runs from cron and deploys whenever the tracked branch moves:

```
*/5 * * * * /home/master/applications/dhrwuuxpcs/public_html/deploy/auto-update.sh
```

Add it under **Application → Cron Job Management** in the Cloudways panel, or with
`crontab -e`. It exits immediately when there is nothing new, so a frequent schedule costs
nothing. Activity is logged to `writable/logs/deploy.log`; a run that finds nothing writes
nothing, so anything in that file is a real deploy.

A push to the branch is therefore all that is needed to ship. Nothing is typed on the server.

### Manual

```bash
APP_NAME=dhrwuuxpcs ~/applications/dhrwuuxpcs/public_html/deploy/cloudways.sh update
```

### First install on a new application

```bash
APP_NAME=<app folder> \
DB_NAME='<db>' DB_USER='<user>' DB_PASS='<password>' \
APP_URL='https://crm.example.com' \
bash <(curl -fsSL https://raw.githubusercontent.com/cerebrumtech/SyncCRM/main/deploy/cloudways.sh) install
```

Prefix with `SEED=1` for the demo workspace. Afterwards set **Webroot** to
`public_html/public` and **Varnish** to *Disabled* in the panel — the folder must exist
before the Webroot can point at it, so this comes after the install, not before.

Get the database values from **Application → Access Details → MySQL Access**.

### What a deploy does

1. Fetches the branch and resets the checkout to it, remembering the previous commit.
2. **Compile-checks every PHP file with the server's own PHP.** This is the authority on
   whether the code runs here — a local check on PHP 8 cannot tell you.
3. If anything fails to parse, **rolls the checkout back** to the previous commit and exits
   non-zero, before touching the database. The site keeps serving the last good version.
4. Ensures `.env` is readable by the web server's group and `writable/` is writable.
5. Applies the schema (idempotent — safe to run repeatedly).

There is no build step and no Composer. The CSS is pre-compiled into `public/assets`.

## Staying on PHP 7.4

The code must parse on 7.4, which means no `match()`, no throw expressions, no `?->`, no
constructor promotion, no `mixed`/`never`/union types, no attributes, no enums. String
helpers like `str_contains` are polyfilled in `kernel/polyfill.php`.

Two safeguards, in order of trustworthiness:

- **The server's `php -l`, run by every deploy.** This is the one that counts. Static
  analysis missed genuine 7.4 incompatibilities three times during the port; this check
  caught each of them.
- A local AST scan against the 7.4 grammar, useful before pushing but not conclusive.

If a deploy fails with a parse error, the named construct is PHP 8 syntax. Rewrite it in
7.4 form; the rollback means the site is still up while you do.

Moving the server to PHP 8 remains worthwhile — the app already runs there unchanged — but
it is a server-wide change affecting every application, so it is not a decision this app
can make alone.

## Blast radius

The installer writes only inside one application folder: code in `public_html`, uploads in
`private_html/uploads`, and that application's own MySQL database. It uses no root access.
It changes no nginx, Apache, Varnish, PHP-FPM or MySQL configuration, and never changes the
server's PHP version. It refuses to run when it can see several applications and none is
named, and refuses to overwrite a `public_html` belonging to something else.

The two panel settings (Webroot, Varnish) are per-application.

## Troubleshooting

Start here, always:

```bash
tail -n 40 ~/applications/dhrwuuxpcs/public_html/writable/logs/*.log
```

Uncaught errors are written there with class, message, file, line and a stack trace. The
browser deliberately shows only "Something went wrong" in production.

| Symptom | Cause | Fix |
|---|---|---|
| 403 from nginx | Webroot still `public_html/`, or app not installed | Set Webroot to `public_html/public` |
| 404 from nginx on every route | Same as above | As above |
| "Something went wrong", log says `Access denied for user ''@'localhost'` | `.env` unreadable by PHP-FPM | `chgrp www-data .env && chmod 640 .env` |
| "Something went wrong", log says `.env is not readable by <user>` | Same cause, now reported directly | As above |
| Deploy stops at "does not compile" | PHP 8 syntax in the code | Rewrite in 7.4 form; site already rolled back |
| Stale pages, login loops | Varnish enabled | Disable Varnish for this app |
| Styles missing | Wrong Webroot, or Varnish cache | Fix Webroot, purge Varnish |
| Uploads fail | `writable/` or `private_html/uploads` not writable | `chmod -R 775 writable` |
| Blank page, nothing in app log | PHP fatal before the app booted | `tail ~/applications/dhrwuuxpcs/logs/php-app.error.log` |

### No shell access?

`https://crm.syncworks.app/install?key=<app.installKey from .env>` creates the tables from
the browser. Clear `app.installKey` in `.env` afterwards.

## Demo data

```bash
cd ~/applications/dhrwuuxpcs/public_html && php schema/install.php --seed
```

Non-destructive: it contains no `TRUNCATE`, `DELETE` or `DROP`, and refuses to run twice by
checking for its own demo organisation first. It can only add a separate demo workspace
alongside real data.

Sign in as `owner@syncworkstech.com` / `password123` (also `admin@`, `rahul@`, `priya@`).

## Branches

`main` and `php-codeigniter` hold identical content. `main` is the default branch; the
server currently tracks `php-codeigniter`, a historical name from an abandoned CodeIgniter
attempt — the app uses no framework. The Next.js implementation that preceded all of this
is still in `main`'s history, ending at `ce5a6a3`.
