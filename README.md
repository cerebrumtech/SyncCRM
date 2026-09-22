# SyncCRM

Pipeline-first CRM for SyncWorks Technologies Pvt. Ltd., modelled on Zoho Bigin. Built with **PHP 8.2+ / CodeIgniter 4 / MySQL**, styled with the SyncWorkstech brand, and hosted on Cloudways as a normal PHP application.

## Stack

- CodeIgniter 4.7 (MVC, server-rendered views, CSRF protection, file sessions)
- MySQL 8 / MariaDB 10.6+ (`app/Database/Migrations`)
- Tailwind CSS 4 compiled once into `public/assets/app.css` (no build step on the server), SortableJS for the Kanban board, a small vanilla `public/assets/app.js`
- Indian defaults: ₹ with lakh/crore grouping, DD/MM/YYYY, IST (`app.appTimezone`)

## Run locally

```bash
composer install
cp .env.example .env            # set database.default.* and app.baseURL
php spark migrate               # creates the tables
php spark db:seed DemoSeeder    # optional demo workspace (owner@syncworkstech.com / password123)
php spark serve                 # http://localhost:8080
```

Without the seed, the first visit opens `/setup` to create the organisation and owner account.

## Deploy on Cloudways

See [deploy/CLOUDWAYS.md](deploy/CLOUDWAYS.md) — one pasted SSH command plus two panel settings (Webroot = `public`, Varnish off).

## What's in the MVP

- **Workspace** — organisation, invite links, Owner/Admin/Member roles, teams, record sharing, deactivate with reassignment, audit log
- **Contacts & Companies** — custom fields, tags, notes, files, timeline, duplicate detection (warn or block, configurable) and merge
- **Pipelines & Deals** — multiple pipelines, custom stages with required-field rules, Kanban drag-and-drop, lost reasons, hand-off to another pipeline
- **Products** — catalogue with SKU/price/GST, deal line items with quantity/discount/tax, amount rollup
- **Activities** — tasks, logged calls (with follow-up), meetings, recurrence, reminders, calendar and per-record timeline
- **Dashboard** — pipeline by stage, won/lost trend, sales by rep, activity volume, my tasks; every widget drills down to the filtered list
- **Data** — saved views, CSV export per module, CSV import with column mapping and duplicate handling

Phase 2 (Google Meet, WhatsApp Business inbox) is tracked in Jira project **CRM** at ensurechat.atlassian.net.

## Layout

```
app/Config/Routes.php            all routes (auth filter for the app, admin filter for /settings)
app/Controllers                  one controller per module; Settings/* for admin pages
app/Models                       one model per table (JSON columns cast to arrays)
app/Libraries                    Auth, Permissions, Audit, CustomFields, Tags, Lists (shared queries), Records, Deals, Activities
app/Helpers                      format_helper (₹, dates, phone) and ui_helper (badges, avatars, icons)
app/Views                        layouts, partials (dialogs, record panels, list toolbar) and module views
app/Database/Migrations, Seeds   schema and demo data
public/                          web root: index.php, assets/, brand/
resources/css/app.css            Tailwind source (npm run css to rebuild public/assets/app.css)
deploy/                          Cloudways installer and runbook
```

Uploads are stored under `app.uploadDir` (default `writable/uploads`, on Cloudways `private_html/uploads`) and served through `/files/:id` after an access check.
