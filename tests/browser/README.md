# Browser tests

These drive a real Chromium against a running SyncCRM. They are the only part of this
repository that needs Node — the application itself has no Node, no npm and no build step.

## Once

```bash
cd tests/browser
npm install          # also downloads Chromium
```

If your machine already has a Chromium you'd rather use, set `PW_CHROMIUM` to its path and
skip the download with `npm install --ignore-scripts`.

## Every run

Start the app first (see `SETUP.md`), then:

```bash
bash tests/browser/run.sh
```

**`run.sh` empties the database named in `.env`** — it drops every table, reinstalls the
schema and loads the demo seed, so the suites always start from known data. Use a local
database only.

Point it elsewhere with `BASE=http://127.0.0.1:9000 bash tests/browser/run.sh`.

## The suites

| Suite | Covers |
|---|---|
| `01-workspace` | sign-in, invites, roles, teams, sharing, deactivation, audit log |
| `02-contacts` | contacts and companies, notes, files, timeline, duplicates, merge |
| `03-deals` | pipelines, stages, Kanban drag-and-drop, line items, hand-off |
| `04-activities` | tasks, calls, meetings, recurrence, calendar |
| `05-data` | custom fields, saved views, CSV export, CSV import with mapping |
| `06-seeded` | the demo workspace renders on every screen |

Pre-launch audits, run by name:

| Suite | Covers |
|---|---|
| `audit-01-security` | session handling, CSRF, access control, sign-in throttling |
| `audit-02-validation` | required fields, lengths, numbers, dates, malformed input |
| `audit-03-behaviour` | double submits, file-upload safety, empty states, sign-out |
| `audit-04-ui` | dialog surfaces, padding, touch targets, screenshots into `tmp/shots` |
| `audit-05-crawl` | every page, tab and filter as Owner and Member, watching for PHP notices |
| `audit-06-inline` | every screen still works under the Content-Security-Policy |
| `audit-07-workflows` | money maths, deletes with children, stage rules, ownership |
| `audit-08-dialogs` | all 18 dialogs open, submit and close |
| `audit-09-import` | the CSV import screen end to end |

`verify-throttle` and `verify-xss-fix` re-prove two specific fixes. `scratch/` holds the
one-off probes used while chasing individual bugs; they are kept for reference and are not
part of any run.
