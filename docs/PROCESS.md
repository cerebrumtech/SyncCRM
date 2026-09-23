# How changes are made to SyncCRM

SyncCRM is live and the sales team uses it daily. Every change follows this process. It
exists so that nothing reaches production unreviewed, and so anyone can reconstruct why a
change was made months later.

Jira project **CRM** at ensurechat.atlassian.net. Change history in
[`CHANGELOG.md`](../CHANGELOG.md). Deployment mechanics in
[`deploy/CLOUDWAYS.md`](../deploy/CLOUDWAYS.md).

## The flow

```
1. INTAKE      A bug report, a feature idea, or a proposal
                     ↓
2. TICKET      CRM ticket: what, why, risk, plan, how it will be tested
               label: needs-approval                    status: To Do
                     ↓
3. APPROVAL    The product owner decides: approved / on-hold / declined
                     ↓
4. BUILD       Written and tested locally against real MySQL and a real browser
               label: approved                          status: In Progress
                     ↓
5. DEPLOY      Push → cron deploys → compile check → rollback if it will not parse
                     ↓
6. VERIFY      Specific steps to click on the live site; the owner confirms
               label: awaiting-verification             status: In Progress
                     ↓
7. RECORD      CHANGELOG.md entry, dates on the ticket
                                                        status: Done
```

**A ticket is Done only after someone has seen it working on the live site.** Not when it
is pushed, not when it is deployed. Whoever writes the code cannot be the one who confirms
it works in production.

## Ticket conventions

The project has only three statuses — To Do, In Progress, Done — and no Bug issue type, so
labels carry the rest.

| Label | Meaning |
|---|---|
| `needs-approval` | Written up, waiting on a decision. No work happens. |
| `approved` | Cleared to build |
| `on-hold` | Worth doing, not now |
| `awaiting-verification` | Deployed, waiting for confirmation on the live site |
| `bug` | Something is broken |
| `feature` | New capability |
| `improvement` | Existing capability made better |
| `chore` | Infrastructure, docs, tooling — no user-visible change |
| `risk-schema` | Changes the database structure — see "Schema changes" |

**Dates.** Start date when work begins, Due date set at approval, and a `## Log` section in
the description with a dated line per transition:

```
## Log
- 2026-09-24 Reported: deal value shows ₹0 after editing line items
- 2026-09-24 Approved
- 2026-09-25 Deployed (a3f9c21)
- 2026-09-25 Verified live
```

**Priority.** Highest: production broken or data at risk. High: blocks daily sales work.
Medium: normal. Low: nice to have.

**Workstreams** group tickets into phases and carry the roadmap-level timeline.

## Rules for a live application

**Nothing ships untested.** The browser suite runs against a real MySQL database and a real
browser before every push. Anything not covered by a test is stated explicitly on the
ticket as untested.

**Small releases.** One ticket per deploy wherever possible. A large release that misbehaves
is far harder to diagnose than several small ones.

**Deploy timing.** Low-risk changes go out as soon as they are approved and tested. Anything
touching the database schema, authentication, or deal data waits until after 20:00
Asia/Kolkata, so a problem lands when nobody is mid-workflow.

**Schema changes** are the one class that cannot be rolled back by redeploying, so they get
extra handling:

- Rehearsed on staging first (CRM-22), never straight to production
- Database backup taken immediately before
- Additive only: new tables, new nullable columns
- A column is never dropped in the same release that stops using it. Release one stops
  writing it; a later release removes it. This keeps a rollback possible in between.
- Labelled `risk-schema` and deployed after hours

**Emergencies.** If the site is down, data is being lost, or there is a security hole, the
fix is made and deployed immediately without waiting for approval. The owner is told at
once and the ticket is written afterwards with a full account of what happened and why.
This applies to genuine emergencies only — everything else waits.

## What protects production

Three layers, none of which replaces the others:

1. **Local testing** — real MySQL, real browser, before the push.
2. **The compile check and rollback** — every deploy compile-checks with the server's own
   PHP 7.4 and restores the previous commit if the code will not parse. This catches syntax
   and version-compatibility errors. It does **not** catch logic errors.
3. **Verification on the live site** — the only thing that catches a change that runs
   perfectly and does the wrong thing.

Layer 3 is the one people skip. It is the one that matters most.

## Who does what

**The product owner** approves or declines tickets, confirms changes on the live site, and
reports problems with the steps taken and what was seen.

**Whoever is building** writes the ticket before writing code, tests before pushing, gives
specific verification steps rather than "please check it works", states what was not
tested, and updates `CHANGELOG.md` with every deployed change.
