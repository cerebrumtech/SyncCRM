# Changelog

Every change that reaches https://crm.syncworks.app, newest first. Each entry carries its
Jira ticket, the commit, and the dates it moved through the process in `docs/PROCESS.md`.

Dates are the date the work landed, in Asia/Kolkata.

---

## 2026-09-23

- **CRM-29** `chore` Deploy on push instead of by hand. `deploy/auto-update.sh` runs from
  cron and deploys when the tracked branch moves; `cloudways.sh` now records the running
  commit and restores it if the new code fails the compile check, so a commit that cannot
  parse on PHP 7.4 leaves the site on the last good version. (`bede831`)
  *Deployed 23 Sep · the cron itself is CRM-32, not yet enabled*

- **CRM-29** `chore` Documented how the application runs and is deployed:
  `deploy/CLOUDWAYS.md` as the operations reference, plus a request-flow diagram and
  module-authoring steps in the README. (`bede831`)

- **CRM-29** `bug` Site returned "Something went wrong" on every page. The installer wrote
  `.env` with mode 600 as the SSH user, so PHP-FPM — which runs as the application user —
  could not read it, fell back to empty settings and failed with MySQL's
  `Access denied for user ''@'localhost'`. `.env` is now group-readable by the web
  server's group, applied on update as well as install. `Env::load()` no longer treats an
  unreadable file as an absent one, and a connection with blank credentials now says the
  `.env` was not read. (`5f63e46`)
  *Reported 23 Sep · Fixed 23 Sep · Verified live 23 Sep*

- **CRM-29** `bug` Install aborted with a parse error: `Records::load()` used a PHP 8 throw
  expression, which PHP 7.4 rejects. Rewritten as a statement. Caught by the compile check
  before any database change. (`5a1e764`)
  *Reported 23 Sep · Fixed 23 Sep*

- **CRM-31** `chore` Rebuilt framework-free so the app runs on the server's PHP 7.4.33.
  CodeIgniter 4 was dropped after Cloudways confirmed the PHP version is server-wide and
  cannot be set per application. Replaced with a ~1,500-line kernel: router, PDO query
  builder, model layer, view renderer, session, CSRF, schema installer. No Composer, no
  build step. (`adae5e8`, `cd1cd25`)

## 2026-09-22

- **CRM-30** `chore` Migrated from Next.js + PostgreSQL to PHP + MySQL, after the original
  Node + PM2 + Apache-proxy design proved unworkable: Cloudways fronts PHP-FPM with nginx
  and never reads `.htaccess`, so the reverse proxy had no effect. (`5da29cd`, `d3bc994`,
  `11d1b50`, `be18bce`)

- **CRM-1** `feature` Initial SyncCRM MVP — workspace and users, contacts and companies,
  pipelines and deals, products, activities, dashboard, saved views, CSV import/export.
  Built on Next.js and PostgreSQL; that implementation remains in `main`'s history ending
  at `ce5a6a3`, tagged `nextjs-mvp`.
