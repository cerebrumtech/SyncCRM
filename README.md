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

## Deploy on Cloudways

See [deploy/CLOUDWAYS.md](deploy/CLOUDWAYS.md). One pasted SSH command, then set the Webroot to `public_html/public` and turn Varnish off.

## What's in it

- **Workspace** — organisation, invite links, Owner/Admin/Member roles, teams, record sharing, deactivate with reassignment, audit log
- **Contacts & Companies** — custom fields, tags, notes, files, timeline, duplicate detection (warn or block) and merge
- **Pipelines & Deals** — multiple pipelines, custom stages with required-field rules, Kanban drag-and-drop, lost reasons, hand-off to another pipeline
- **Products** — catalogue with SKU/price/GST, deal line items with quantity/discount/tax, amount rollup
- **Activities** — tasks, logged calls with follow-ups, meetings, recurrence, reminders, calendar and per-record timeline
- **Dashboard** — pipeline by stage, won/lost trend, sales by rep, activity volume, my tasks; every widget drills down
- **Data** — saved views, CSV export per module, CSV import with column mapping and duplicate handling

Indian defaults throughout: ₹ with lakh and crore grouping, DD/MM/YYYY dates, Asia/Kolkata time.

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

## Tracking

Jira project **CRM** (SyncCRM) at ensurechat.atlassian.net.
