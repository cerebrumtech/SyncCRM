import Link from "next/link";
import { requireUser } from "@/lib/auth";
import { prisma } from "@/lib/db";
import { activeUsers } from "@/lib/queries";
import { activityInclude, toActivityRow } from "@/lib/activity-rows";
import { Card, CardBody, PageHeader } from "@/components/ui/card";
import { Select } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { ActivityList } from "@/components/app/activity-list";
import { ActivityQuickActions } from "@/components/app/activity-form";
import { MonthCalendar } from "./calendar";
import { cn } from "@/lib/cn";
import { IST_OFFSET_MS, istNowIso } from "@/lib/format";
import type { Prisma } from "@/generated/prisma/client";

export const metadata = { title: "Activities" };

function istDayStart(d: Date) {
  const shifted = new Date(d.getTime() + IST_OFFSET_MS);
  return new Date(Date.UTC(shifted.getUTCFullYear(), shifted.getUTCMonth(), shifted.getUTCDate()) - IST_OFFSET_MS);
}

export default async function ActivitiesPage(props: PageProps<"/activities">) {
  const me = await requireUser();
  const sp = await props.searchParams;
  const view = sp.view === "calendar" ? "calendar" : "list";
  const range = typeof sp.range === "string" && ["today", "upcoming", "overdue", "completed", "all"].includes(sp.range) ? sp.range : "upcoming";
  const type = typeof sp.type === "string" && ["TASK", "CALL", "EVENT"].includes(sp.type) ? (sp.type as "TASK" | "CALL" | "EVENT") : undefined;
  const assignee = typeof sp.assignee === "string" ? sp.assignee : "me";
  const focus = typeof sp.focus === "string" ? sp.focus : undefined;
  const istNow = istNowIso();
  const month = typeof sp.month === "string" && /^\d{4}-\d{2}$/.test(sp.month) ? sp.month : istNow.slice(0, 7);

  const now = new Date();
  const today = istDayStart(now);
  const tomorrow = new Date(today.getTime() + 86_400_000);

  const where: Prisma.ActivityWhereInput = {
    orgId: me.orgId,
    ...(type ? { type } : {}),
    ...(assignee === "me" ? { assigneeId: me.id } : assignee && assignee !== "all" ? { assigneeId: assignee } : {}),
  };
  const users = await activeUsers(me.orgId);

  if (view === "calendar") {
    const [y, m] = month.split("-").map(Number);
    const start = new Date(Date.UTC(y!, m! - 1, 1) - IST_OFFSET_MS);
    const end = new Date(Date.UTC(y!, m!, 1) - IST_OFFSET_MS);
    const rows = await prisma.activity.findMany({ where: { ...where, dueAt: { gte: start, lt: end } }, include: activityInclude, orderBy: { dueAt: "asc" } });
    return (
      <>
        <Header view={view} range={range} type={type} assignee={assignee} users={users} meId={me.id} />
        <MonthCalendar month={month} todayKey={istNow.slice(0, 10)} items={rows.map((a) => toActivityRow(a, me))} users={users} meId={me.id} />
      </>
    );
  }

  const rangeWhere: Prisma.ActivityWhereInput =
    range === "today"
      ? { status: "OPEN", dueAt: { gte: today, lt: tomorrow } }
      : range === "overdue"
        ? { status: "OPEN", dueAt: { lt: now } }
        : range === "completed"
          ? { status: "COMPLETED" }
          : range === "all"
            ? {}
            : { status: "OPEN", OR: [{ dueAt: { gte: today } }, { dueAt: null }] };

  const rows = await prisma.activity.findMany({
    where: { ...where, ...rangeWhere, ...(focus ? { OR: [{ id: focus }, { AND: [rangeWhere] }] } : {}) },
    include: activityInclude,
    orderBy: range === "completed" ? [{ completedAt: "desc" }] : [{ dueAt: "asc" }, { createdAt: "desc" }],
    take: 300,
  });
  const items = rows.map((a) => toActivityRow(a, me));

  const counts = await Promise.all([
    prisma.activity.count({ where: { ...where, status: "OPEN", dueAt: { gte: today, lt: tomorrow } } }),
    prisma.activity.count({ where: { ...where, status: "OPEN", dueAt: { lt: now } } }),
  ]);

  return (
    <>
      <Header view={view} range={range} type={type} assignee={assignee} users={users} meId={me.id} counts={{ today: counts[0], overdue: counts[1] }} />
      <Card>
        <CardBody>
          <ActivityList items={items} users={users} meId={me.id} focusId={focus} emptyText={range === "overdue" ? "Nothing overdue. Nice." : "No activities in this view."} />
        </CardBody>
      </Card>
    </>
  );
}

function Header({ view, range, type, assignee, users, meId, counts }: { view: string; range: string; type?: string; assignee: string; users: { id: string; name: string }[]; meId: string; counts?: { today: number; overdue: number } }) {
  const link = (over: Record<string, string | undefined>) => {
    const u = new URLSearchParams();
    for (const [k, v] of Object.entries({ view, range, type, assignee, ...over })) if (v) u.set(k, v);
    return `/activities?${u}`;
  };
  return (
    <>
      <PageHeader
        title="Activities"
        subtitle="Tasks, calls and meetings across your contacts, companies and deals."
        actions={
          <>
            <div className="flex rounded-md border border-line bg-white p-0.5 text-sm">
              <Link href={link({ view: "list" })} className={cn("rounded px-3 py-1", view === "list" ? "bg-navy text-white" : "text-ink-700")}>
                List
              </Link>
              <Link href={link({ view: "calendar" })} className={cn("rounded px-3 py-1", view === "calendar" ? "bg-navy text-white" : "text-ink-700")}>
                Calendar
              </Link>
            </div>
            <ActivityQuickActions users={users} meId={meId} />
          </>
        }
      />
      <form className="mb-4 flex flex-wrap items-center gap-2">
        <input type="hidden" name="view" value={view} />
        {view === "list" && (
          <div className="flex rounded-md border border-line bg-white p-0.5 text-sm">
            {[
              ["today", `Today${counts ? ` (${counts.today})` : ""}`],
              ["upcoming", "Upcoming"],
              ["overdue", `Overdue${counts ? ` (${counts.overdue})` : ""}`],
              ["completed", "Completed"],
              ["all", "All"],
            ].map(([k, label]) => (
              <Link key={k} href={link({ range: k })} className={cn("rounded px-3 py-1", range === k ? "bg-primary text-white" : "text-ink-700", k === "overdue" && counts?.overdue && range !== k && "text-danger")}>
                {label}
              </Link>
            ))}
            <input type="hidden" name="range" value={range} />
          </div>
        )}
        <Select name="type" defaultValue={type ?? ""} className="w-36">
          <option value="">All types</option>
          <option value="TASK">Tasks</option>
          <option value="CALL">Calls</option>
          <option value="EVENT">Meetings</option>
        </Select>
        <Select name="assignee" defaultValue={assignee} className="w-44">
          <option value="me">Assigned to me</option>
          <option value="all">Everyone</option>
          {users.map((u) => (
            <option key={u.id} value={u.id}>
              {u.name}
            </option>
          ))}
        </Select>
        <Button type="submit" variant="secondary">
          Apply
        </Button>
      </form>
    </>
  );
}
