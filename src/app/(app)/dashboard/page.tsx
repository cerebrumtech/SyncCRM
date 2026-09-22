import Link from "next/link";
import { requireUser } from "@/lib/auth";
import { prisma } from "@/lib/db";
import { activeUsers } from "@/lib/queries";
import { activitiesFor } from "@/lib/activity-rows";
import { formatCompactINR, IST_OFFSET_MS, istNowIso, toNumber } from "@/lib/format";
import { Card, CardBody, CardHeader, PageHeader, Stat } from "@/components/ui/card";
import { Select } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { BarList, WonLostChart, type MonthPoint } from "@/components/app/charts";
import { ActivityList } from "@/components/app/activity-list";
import type { Prisma } from "@/generated/prisma/client";

export const metadata = { title: "Dashboard" };

const RANGES = {
  month: "This month",
  last30: "Last 30 days",
  quarter: "This quarter",
  year: "This year",
  all: "All time",
} as const;
type RangeKey = keyof typeof RANGES;

function rangeStart(key: RangeKey, istNow: string): Date | null {
  const [y, m, d] = istNow.slice(0, 10).split("-").map(Number) as [number, number, number];
  const ist = (yy: number, mm: number, dd: number) => new Date(Date.UTC(yy, mm, dd) - IST_OFFSET_MS);
  switch (key) {
    case "month":
      return ist(y, m - 1, 1);
    case "last30":
      return ist(y, m - 1, d - 30);
    case "quarter":
      return ist(y, Math.floor((m - 1) / 3) * 3, 1);
    case "year":
      return ist(y, 0, 1);
    default:
      return null;
  }
}

export default async function DashboardPage(props: PageProps<"/dashboard">) {
  const me = await requireUser();
  const sp = await props.searchParams;
  const istNow = istNowIso();
  const range: RangeKey = typeof sp.range === "string" && sp.range in RANGES ? (sp.range as RangeKey) : "month";
  const owner = typeof sp.owner === "string" && sp.owner ? sp.owner : undefined;
  const since = rangeStart(range, istNow);
  const now = new Date();

  const pipelines = await prisma.pipeline.findMany({ where: { orgId: me.orgId }, orderBy: { position: "asc" }, include: { stages: { orderBy: { position: "asc" } } } });
  const pipeline = pipelines.find((p) => p.id === sp.pipeline) ?? pipelines.find((p) => p.isDefault) ?? pipelines[0];

  const base: Prisma.DealWhereInput = { orgId: me.orgId, ...(owner ? { ownerId: owner } : {}) };
  const closedIn: Prisma.DealWhereInput = since ? { closedAt: { gte: since } } : {};
  const sixMonthsAgo = (() => {
    const [y, m] = istNow.slice(0, 7).split("-").map(Number) as [number, number];
    return new Date(Date.UTC(y, m - 6, 1) - IST_OFFSET_MS);
  })();
  const todayStart = new Date(Date.UTC(+istNow.slice(0, 4), +istNow.slice(5, 7) - 1, +istNow.slice(8, 10)) - IST_OFFSET_MS);
  const tomorrowStart = new Date(todayStart.getTime() + 86_400_000);

  const [openDeals, wonAgg, lostAgg, closedRecent, users, activityCounts, myOverdue, myToday, myOpen, upcoming, tasksByStatus] = await Promise.all([
    prisma.deal.findMany({ where: { ...base, status: "OPEN", ...(pipeline ? { pipelineId: pipeline.id } : {}) }, select: { stageId: true, amount: true, ownerId: true } }),
    prisma.deal.aggregate({ where: { ...base, status: "WON", ...closedIn }, _sum: { amount: true }, _count: true }),
    prisma.deal.aggregate({ where: { ...base, status: "LOST", ...closedIn }, _sum: { amount: true }, _count: true }),
    prisma.deal.findMany({ where: { ...base, status: { in: ["WON", "LOST"] }, closedAt: { gte: sixMonthsAgo } }, select: { status: true, amount: true, closedAt: true, ownerId: true } }),
    activeUsers(me.orgId),
    prisma.activity.groupBy({ by: ["type"], where: { orgId: me.orgId, ...(owner ? { assigneeId: owner } : {}), ...(since ? { createdAt: { gte: since } } : {}) }, _count: true }),
    prisma.activity.count({ where: { orgId: me.orgId, assigneeId: me.id, status: "OPEN", dueAt: { lt: now } } }),
    prisma.activity.count({ where: { orgId: me.orgId, assigneeId: me.id, status: "OPEN", dueAt: { gte: todayStart, lt: tomorrowStart } } }),
    prisma.activity.count({ where: { orgId: me.orgId, assigneeId: me.id, status: "OPEN" } }),
    activitiesFor(me.orgId, { assigneeId: me.id, status: "OPEN", OR: [{ dueAt: { gte: todayStart } }, { dueAt: null }] }, me, 8),
    prisma.activity.groupBy({ by: ["status"], where: { orgId: me.orgId, type: "TASK", ...(owner ? { assigneeId: owner } : {}), ...(since ? { createdAt: { gte: since } } : {}) }, _count: true }),
  ]);

  const openValue = openDeals.reduce((s, d) => s + toNumber(d.amount), 0);
  const stageRows = (pipeline?.stages ?? [])
    .filter((s) => !s.isWon && !s.isLost)
    .map((s) => {
      const inStage = openDeals.filter((d) => d.stageId === s.id);
      const value = inStage.reduce((sum, d) => sum + toNumber(d.amount), 0);
      return { key: s.id, label: s.name, value, display: formatCompactINR(value), sub: `${inStage.length} deal${inStage.length === 1 ? "" : "s"}`, href: `/deals?pipeline=${pipeline!.id}&view=list&status=OPEN&stage=${s.id}${owner ? `&owner=${owner}` : ""}` };
    });

  // Won revenue by rep for the selected range
  const wonInRange = await prisma.deal.groupBy({ by: ["ownerId"], where: { ...base, status: "WON", ...closedIn }, _sum: { amount: true }, _count: true });
  const byUser = wonInRange
    .map((g) => ({ user: users.find((u) => u.id === g.ownerId), value: toNumber(g._sum.amount), count: g._count }))
    .filter((r) => r.user)
    .sort((a, b) => b.value - a.value)
    .map((r) => ({ key: r.user!.id, label: r.user!.name, value: r.value, display: formatCompactINR(r.value), sub: `${r.count} won`, href: `/deals?view=list&status=WON&owner=${r.user!.id}` }));

  // Monthly won/lost, last 6 months
  const months: MonthPoint[] = [];
  for (let i = 5; i >= 0; i--) {
    const [y, m] = istNow.slice(0, 7).split("-").map(Number) as [number, number];
    const d = new Date(Date.UTC(y, m - 1 - i, 1));
    const key = d.toISOString().slice(0, 7);
    months.push({ key, label: d.toLocaleDateString("en-GB", { month: "short", timeZone: "UTC" }), won: 0, lost: 0, wonCount: 0, lostCount: 0 });
  }
  for (const d of closedRecent) {
    if (!d.closedAt) continue;
    const key = new Date(d.closedAt.getTime() + IST_OFFSET_MS).toISOString().slice(0, 7);
    const p = months.find((x) => x.key === key);
    if (!p) continue;
    if (d.status === "WON") {
      p.won += toNumber(d.amount);
      p.wonCount++;
    } else {
      p.lost += toNumber(d.amount);
      p.lostCount++;
    }
  }

  const activityRows = (["TASK", "CALL", "EVENT"] as const).map((t) => {
    const c = activityCounts.find((a) => a.type === t)?._count ?? 0;
    return { key: t, label: t === "TASK" ? "Tasks" : t === "CALL" ? "Calls" : "Meetings", value: c, display: String(c), href: `/activities?range=all&assignee=${owner ?? "all"}&type=${t}` };
  });
  const taskDone = tasksByStatus.find((t) => t.status === "COMPLETED")?._count ?? 0;
  const taskOpen = tasksByStatus.find((t) => t.status === "OPEN")?._count ?? 0;
  const wonCount = wonAgg._count;
  const lostCount = lostAgg._count;
  const winRate = wonCount + lostCount > 0 ? Math.round((wonCount / (wonCount + lostCount)) * 100) : null;
  const q = (over: Record<string, string | undefined>) => {
    const u = new URLSearchParams();
    for (const [k, v] of Object.entries({ pipeline: pipeline?.id, range, owner, ...over })) if (v) u.set(k, v);
    return u.toString();
  };

  return (
    <>
      <PageHeader title="Dashboard" subtitle={`Welcome back, ${me.name}. Here's how ${owner ? users.find((u) => u.id === owner)?.name ?? "the team" : "the team"} is doing ${RANGES[range].toLowerCase()}.`} />
      <form className="mb-5 flex flex-wrap items-center gap-2">
        <Select name="pipeline" defaultValue={pipeline?.id ?? ""} className="w-44">
          {pipelines.map((p) => (
            <option key={p.id} value={p.id}>
              {p.name}
            </option>
          ))}
        </Select>
        <Select name="range" defaultValue={range} className="w-40">
          {Object.entries(RANGES).map(([k, v]) => (
            <option key={k} value={k}>
              {v}
            </option>
          ))}
        </Select>
        <Select name="owner" defaultValue={owner ?? ""} className="w-44">
          <option value="">Everyone</option>
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

      <div className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <Stat label={`Open pipeline · ${pipeline?.name ?? ""}`} value={formatCompactINR(openValue)} hint={`${openDeals.length} open deal${openDeals.length === 1 ? "" : "s"}`} href={`/deals?${q({ view: "list", status: "OPEN" })}`} />
        <Stat label="Won" value={formatCompactINR(toNumber(wonAgg._sum.amount))} hint={`${wonCount} deal${wonCount === 1 ? "" : "s"} · ${RANGES[range]}`} href={`/deals?${q({ view: "list", status: "WON", pipeline: undefined })}`} />
        <Stat label="Lost" value={formatCompactINR(toNumber(lostAgg._sum.amount))} hint={`${lostCount} deal${lostCount === 1 ? "" : "s"}${winRate !== null ? ` · ${winRate}% win rate` : ""}`} href={`/deals?${q({ view: "list", status: "LOST", pipeline: undefined })}`} />
        <Stat label="My tasks" value={myOpen} hint={<span className={myOverdue ? "font-medium text-danger" : undefined}>{myOverdue} overdue · {myToday} due today</span>} href="/activities?range=overdue" />
        <Stat label="Tasks completed" value={taskDone} hint={`${taskOpen} still open · ${RANGES[range]}`} href={`/activities?range=completed&type=TASK&assignee=${owner ?? "all"}`} />
      </div>

      <div className="grid gap-5 lg:grid-cols-2">
        <Card>
          <CardHeader title={`Pipeline by stage · ${pipeline?.name ?? ""}`} action={<Link href={`/deals?pipeline=${pipeline?.id}`} className="text-xs text-primary hover:underline">Open board</Link>} />
          <CardBody>
            <BarList rows={stageRows} empty="No open deals in this pipeline." />
          </CardBody>
        </Card>
        <Card>
          <CardHeader title="Won vs lost revenue · last 6 months" />
          <CardBody>
            <WonLostChart points={months} hrefFor={(p, status) => `/deals?view=list&status=${status}${owner ? `&owner=${owner}` : ""}&closed=${p.key}`} />
          </CardBody>
        </Card>
        <Card>
          <CardHeader title={`Sales by rep · ${RANGES[range]}`} />
          <CardBody>
            <BarList rows={byUser} color="#10B981" empty="No deals won in this period." />
          </CardBody>
        </Card>
        <Card>
          <CardHeader title={`Activity volume · ${RANGES[range]}`} />
          <CardBody>
            <BarList rows={activityRows} color="#04A2FB" />
          </CardBody>
        </Card>
        <Card className="lg:col-span-2">
          <CardHeader title="Up next for you" action={<Link href="/activities" className="text-xs text-primary hover:underline">All activities</Link>} />
          <CardBody>
            <ActivityList items={upcoming} users={users} meId={me.id} emptyText="Nothing scheduled. Enjoy the quiet, or create a task." />
          </CardBody>
        </Card>
      </div>
      <p className="mt-6 text-xs text-ink-500">Amounts in ₹ (INR) · dates in IST.</p>
    </>
  );
}
