# Changelog

Every change that reaches https://crm.syncworks.app, newest first. Each entry carries its
Jira ticket, the commit, and the dates it moved through the process in `docs/PROCESS.md`.

Dates are the date the work landed, in Asia/Kolkata.

---

## 2026-09-25

- **CRM-41** `improvement` A contact or company missing from a deal's picker can now be created
  from the picker itself — "+ Add contact…" with whatever you have typed — instead of abandoning
  the deal to go and make it. Only a name is taken; the rest belongs on the record's own page.
  A name that already exists is selected rather than created twice.

- **CRM-39** `improvement` Filter bars apply themselves. A dropdown takes effect the moment it
  changes and a search box half a second after you stop typing, so the Apply button is gone.
  It is only hidden, not removed, so the bars still work without JavaScript.

- **CRM-39** `improvement` A top bar on every screen, not just phones. A **New** button starts
  a Deal, Contact, Company or Activity from wherever you are, and a bell shows how much of your
  own work is due today or already late. `?new=1` now opens the create dialog on all four
  screens, not only Deals.

- **CRM-38** `feature` A deal now names the products it sells. The new-deal dialog lists the
  catalogue and will not save without at least one; each product picked becomes a line item at
  its catalogue price and tax. An amount typed by hand still wins, and left blank the deal is
  valued from the products chosen. "Line items" is now **Products** throughout. Existing deals
  are untouched: 409 of them were imported as enquiries with no product recorded anywhere, and
  there is nothing to backfill them from without inventing figures.

- **CRM-37** `bug` A large enough amount on a deal produced "Something went wrong" and left
  the user on a bare error page, which read as being signed out. The session was never
  touched — that page simply has no navigation on it. Every money column is `decimal(14,2)`,
  so the most any of them holds is ₹999,999,999,999.99; the amount was checked for being
  numeric and not negative, but never for fitting. Anything larger reached MySQL and came
  back as an unhandled "Out of range value". The deal amount, the product price and each
  line item (including the total it produces) are now checked first, and an oversized figure
  is refused with an ordinary form error instead of a 500.

---

## 2026-09-24

- **CRM-36** `feature` `risk-schema` A user can now be limited to the records they own.
  Until now everyone signed in could read every record; `Permissions` governed editing and
  deleting, and nothing governed seeing. Adds `users.visibility` (`all` or `own`) and
  `App\Libraries\Visibility`, applied to the list queries, the CSV export, the activity
  queries, the dashboard figures, the JSON typeahead, and every record's own page — that last
  one matters, because filtering a list still leaves the record reachable by typing its URL.
  It answers 404 there rather than 403, so the reply does not confirm the record exists.
  The organisation's owner is always able to see everything and cannot be restricted. Records
  with no owner stay visible to all. Every existing user defaults to `all`, so nothing changes
  until someone is set otherwise on Settings → Users.

- **CRM-35** `bug` The dashboard counted won deals across every pipeline, so it reported 14
  wins where there are 10 paying customers and a 20% win rate where the real figure is 15%.
  `$openDeals` was scoped to the selected pipeline and the `$agg` closure beside it was not;
  the Onboarding pipeline's own won stage was being counted alongside Sales. The rupee value
  was always correct, because onboarding deals carry no amount, which is why it went unseen.

- **CRM-35** `bug` The dashboard opened on "This month" and read as empty. Every deal closed
  between April and August, so signing in during September showed "Won ₹0 · 0 deals" with all
  ₹7,15,000 of closed business hidden behind a filter. It now opens on all time.

- **CRM-35** `bug` A double-click on Save created the record twice. The submit button is
  disabled a tick late, and every record dialog carries `data-keep-enabled`, which skipped that
  entirely. The form itself is now latched for the life of the page. The duplicate warning and
  "create anyway" still work, because a warning is a fresh page load and clears the latch.

---

## 2026-09-23

- **CRM-35** `chore` The sales hand-off is fetched onto the server by a script that checks
  its own work: `deploy/fetch-handoff.sh` refuses a Drive sign-in page, an empty file, a CSV
  whose header is not the one expected, a workbook that will not open, and a path that would
  escape the target directory. It reports a workbook's tabs so a missing sheet is visible
  before the import runs. File ids stay out of this repository — for a link-shared folder the
  id is the access key and this repository is public.

- **CRM-35** `improvement` The import no longer leaves two of the export's files unused.
  `possible_duplicate_companies.csv` now tags both companies in each suspected pair
  `possible-duplicate` and writes a note on each naming the other, the similarity and why the
  export kept them apart, so the pairs are filterable in the CRM instead of readable only in
  the export folder. `unassigned_phone_numbers.csv` is listed in the import report; those
  numbers belong to no person and no society, so nothing is invented from them.

- **CRM-34** `chore` A deploy can no longer install an incomplete checkout. An empty commit
  passed the compile check, because a loop over no files reports no failures; the installer
  now requires the application's key files and a plausible PHP file count, and rolls back
  when they are absent. Found the hard way: an empty tree was pushed and merged to `main`.

- **CRM-34** `bug` Deleting a company destroyed its contacts and deals; deleting a contact
  destroyed its deals. Three foreign keys shipped as `ON DELETE CASCADE ON UPDATE SET NULL`,
  the two clauses the wrong way round, while the confirmation dialog promised the records
  would only lose the link. Removing one company took 2 contacts, 1 deal, 1 activity, 2 line
  items and every note with it. Now `ON DELETE SET NULL ON UPDATE CASCADE`, with an
  idempotent repair for databases that already have the old rules.

- **CRM-34** `bug` The Content-Security-Policy forbade inline script and silently broke three
  screens: the custom-field Options box never appeared for a dropdown, the stage editor
  stopped pre-ticking required fields, and a linked activity was no longer highlighted. All
  three moved into `app.js`, with tests.

- **CRM-34** `bug` Stored XSS: an uploaded file was served with the type its uploader claimed,
  and images, text and PDFs were served inline, so an ordinary `.svg` carrying `<script>` ran
  on this origin with the viewer's session. Types are now read from the bytes and only a
  short allowlist renders in place.

- **CRM-34** `improvement` Sign-in throttling, security headers (CSP, X-Frame-Options,
  Referrer-Policy, HSTS), and a duplicate-detection key that is no longer invented from a
  phone-number fragment.

## 2026-09-23 (earlier)

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
