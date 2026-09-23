# SyncCRM

Pipeline-first CRM for SyncWorks Technologies Pvt. Ltd., modelled on Zoho Bigin.

Built to run on an ordinary shared PHP host: **PHP 7.4 or newer, MySQL, and nothing else**. There is no framework, no Composer step and no build step on the server, so deployment is a `git clone` plus one command.

## Why no framework

The production server runs PHP 7.4, which every maintained PHP framework has now dropped. Rather than pin an end-of-life framework, the app ships a small kernel of its own under `kernel/` (about 1,500 lines): routing, a PDO query builder, a model layer, views, sessions and CSRF. It has zero third-party dependencies, so there is nothing to patch and nothing that can go end-of-life. The same code runs unchanged on PHP 8.

## Run locally

```bash
cp .env.example .env            # set database.default.* and app.baseURL
php schema/install.php          # creates the tables
php schema/install.php --seed   # optional demo workspace
php -S 127.0.0.1:8080 -t public # http://127.0.0.1:8080
```

Demo sign-in: `owner@syncworkstech.com` / `password123`, plus `admin@`, `rahul@` and `priya@` on the same password. Without the seed, the first visit opens `/setup`.

## Deploy

Live at **https://crm.syncworks.app** on Cloudways. A push to the deployed branch is all a
release takes: a cron on the server runs `deploy/auto-update.sh`, which compile-checks the
code on the server's own PHP and rolls back if it does not parse.

Everything about production — how nginx and PHP-FPM are wired, the panel settings, the
deploy sequence, the PHP 7.4 constraint, troubleshooting — is in
**[deploy/CLOUDWAYS.md](deploy/CLOUDWAYS.md)**. Read it before touching the server.

## What's in it

- **Workspace** — organisation, invite links, Owner/Admin/Member roles, teams, record sharing, deactivate with reassignment, audit log
- **Contacts & Companies** — custom fields, tags, notes, files, timeline, duplicate detection (warn or block) and merge
- **Pipelines & Deals** — multiple pipelines, custom stages with required-field rules, Kanban drag-and-drop, lost reasons, hand-off to another pipeline
- **Products** — catalogue with SKU/price/GST, deal line items with quantity/discount/tax, amount rollup
- **Activities** — tasks, logged calls with follow-ups, meetings, recurrence, reminders, calendar and per-record timeline
- **Dashboard** — pipeline by stage, won/lost trend, sales by rep, activity volume, my tasks; every widget drills down
- **Data** — saved views, CSV export per module, CSV import with column mapping and duplicate handling

Indian defaults throughout: ₹ with lakh and crore grouping, DD/MM/YYYY dates, Asia/Kolkata time.

## How a request flows

```
nginx  ──try_files──▶  public/index.php
                          │  kernel/bootstrap.php  loads .env, autoloads Sync\* and App\*
                          │  session()->start()    cookie session, HttpOnly + SameSite
                          │  Csrf::verify()        every non-GET request
                          ▼
                       Sync\Router          matches app/Config/Routes.php
                          │                 runs 'auth' / 'admin' filters
                          ▼
                       App\Controllers\*   one controller per module
                          │  model(FooModel::class)->…   via kernel/Database/QueryBuilder
                          ▼
                       view('name', $data)  kernel/View renders app/Views + a layout
```

`public/index.php` catches everything: `SecurityError` → 403, `PageNotFound` → 404, any
other `Throwable` → 500 with the class, message, file, line and trace appended to
`writable/logs/error-YYYY-MM-DD.log`. The browser only ever sees a generic message in
production, so **the log is where you look**.

Every row is scoped to an organisation. Models expose `findInOrg()` and friends rather than
raw finders, so a missing `organization_id` filter is hard to write by accident.

## Adding a module

1. Table in `schema/schema.sql` (the installer applies it; it is idempotent).
2. Model in `app/Models` — set `$casts` for JSON and integer columns.
3. Controller in `app/Controllers`, extending `BaseController` for `fail()` and the current
   user/organisation helpers.
4. Route in `app/Config/Routes.php`, with the `auth` filter (and `admin` where relevant).
5. Views in `app/Views`; layouts and partials already handle chrome and navigation.

## Layout

```
public/index.php        front controller; the web server's document root is public/
kernel/                 the mini-framework: Router, Model, Database/QueryBuilder, View, Http/*
app/Config/Routes.php   every route
app/Controllers         one per module, Settings/* for admin pages
app/Models              one per table; $casts maps JSON and integer columns
app/Libraries           Auth, Permissions, Audit, CustomFields, Tags, Lists, Records, Deals, Activities
app/Helpers             format_helper (₹, dates, phone) and ui_helper (badges, avatars, icons)
app/Views               layouts, partials and module views
schema/schema.sql       the database schema
schema/install.php      creates tables, --seed loads demo data
writable/               logs and uploads (uploads move outside the web root in production)
```

## Security notes

- Every SQL value is a bound parameter. The few raw fragments are quoted literals or integer casts.
- All output goes through `esc()`. CSRF is verified on every non-GET request.
- Passwords use `password_hash` with bcrypt. Sessions are HttpOnly, SameSite=Lax, and Secure over HTTPS.
- Uploads are stored outside the web root and served through `/files/:id` after an access check.
- `.env` sits above the document root, so it is not reachable over the web.

PHP 7.4 itself is past end of life and no longer receives security patches. Moving the server to PHP 8 remains worthwhile; this app already runs there unchanged.

## Tracking and process

Jira project **CRM** (SyncCRM) at ensurechat.atlassian.net. `CRM-29` carries the deployment
record.

The application is live and in daily use, so changes follow a defined process:
**[docs/PROCESS.md](docs/PROCESS.md)** — how work is proposed, approved, tested, deployed
and verified, and the extra handling schema changes get. Every deployed change is recorded
in **[CHANGELOG.md](CHANGELOG.md)**.

`main` and `php-codeigniter` hold identical content; the server tracks the latter, whose
name is historical — there is no CodeIgniter here. The earlier Next.js/PostgreSQL
implementation remains in `main`'s history, ending at `ce5a6a3`.
