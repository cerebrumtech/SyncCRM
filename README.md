# SyncCRM

Pipeline-first CRM for SyncWorks Technologies Pvt. Ltd., modelled on Zoho Bigin. Phase 1 (MVP) covers the core CRM; Google Meet and the WhatsApp Business inbox come in Phase 2 (see the PRD).

## Stack

- Next.js 16 (App Router, Server Actions) · React 19 · TypeScript
- Prisma 7 + PostgreSQL 16
- Tailwind CSS 4 with the SyncWorkstech brand tokens (Inter, navy `#1B243E`, primary `#0068FF`)
- No external auth provider: email/password with database sessions and invite links

## Run locally

```bash
pnpm install
cp .env.example .env            # set DATABASE_URL, APP_URL, UPLOAD_DIR
pnpm db:migrate                 # applies prisma/migrations and generates the client
pnpm db:seed                    # optional demo workspace (owner@syncworkstech.com / password123)
pnpm dev                        # http://localhost:3000
```

Without the seed, the first visit opens `/setup` to create the organisation and owner account.

## Deploy with Docker (any VPS)

```bash
git clone https://github.com/cerebrumtech/SyncCRM && cd SyncCRM
export POSTGRES_PASSWORD='a-strong-password' APP_URL='https://crm.example.com'
docker compose up -d --build          # builds the app, starts PostgreSQL, applies migrations
docker compose exec app pnpm db:seed  # optional demo data
```

The app listens on port 3000 (`PORT=8080 docker compose up` to change); put it behind HTTPS (Caddy, nginx or the host's load balancer) and point `APP_URL` at that address so invite links are correct. Database and uploads live in the `pgdata` and `uploads` volumes. Any platform that runs a Dockerfile plus a PostgreSQL 16 database (Railway, Render, Fly.io, AWS) works the same way — set `DATABASE_URL`, `APP_URL` and `UPLOAD_DIR`.

Project tracking: Jira project **CRM** (SyncCRM) at ensurechat.atlassian.net.

## Scripts

| Script | What it does |
|---|---|
| `pnpm dev` / `pnpm build` / `pnpm start` | Next.js dev server / production build / serve |
| `pnpm lint` · `pnpm typecheck` | ESLint · `tsc --noEmit` |
| `pnpm db:migrate` | `prisma migrate deploy` + `prisma generate` |
| `pnpm db:migrate:dev` | create a new migration from schema changes (development) |
| `pnpm db:seed` | load the demo workspace |
| `pnpm db:studio` | Prisma Studio |

## What's in the MVP

- **Workspace** — organisation, invite links, Owner/Admin/Member roles, teams, record sharing, deactivate with reassignment, audit log
- **Contacts & Companies** — custom fields, tags, notes, files, timeline, duplicate detection (warn or block, configurable) and merge
- **Pipelines & Deals** — multiple pipelines, custom stages with required-field rules, Kanban drag-and-drop, lost reasons, hand-off to another pipeline
- **Products** — catalogue with SKU/price/GST, deal line items with quantity/discount/tax, amount rollup
- **Activities** — tasks, logged calls (with follow-up), meetings, recurrence, reminders, calendar and per-record timeline
- **Dashboard** — pipeline by stage, won/lost trend, sales by rep, activity volume, my tasks; every widget drills down to the filtered list
- **Data** — saved views, CSV export per module, CSV import with column mapping and duplicate handling

## Layout

```
prisma/schema.prisma          data model (every table is scoped by organisation)
prisma/seed.ts                demo data
src/app/(auth)                login, first-run setup, invite acceptance
src/app/(app)                 the signed-in app (dashboard, contacts, companies, deals, products, activities, settings)
src/app/api                   file download and CSV export route handlers
src/lib/actions               server actions (all mutations; each checks the caller's permissions)
src/lib/permissions.ts        role and record-level access rules
src/components/ui             brand-styled primitives
src/components/app            CRM widgets (forms, Kanban, timeline, charts)
src/proxy.ts                  redirects signed-out visitors to /login
```

Uploaded files are stored under `UPLOAD_DIR` (default `./uploads`) and served through `/api/files/:id` after an access check.
