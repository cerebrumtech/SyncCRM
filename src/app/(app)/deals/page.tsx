import Link from "next/link";
import { requireUser } from "@/lib/auth";
import { prisma } from "@/lib/db";
import { activeUsers, tagNames } from "@/lib/queries";
import { getFieldDefs } from "@/lib/custom-fields";
import { formatDate, formatINR, fullName, toDateInput, toNumber } from "@/lib/format";
import { Card, EmptyState, PageHeader } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Avatar } from "@/components/ui/avatar";
import { Input, Select } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { DealBoard, NewDealButton, type BoardDeal } from "./client";
import { PipelineSwitcher } from "./pipeline-switcher";
import { SavedViews } from "@/components/app/saved-views";
import { savedViewsFor } from "@/lib/queries";
import { isAdmin } from "@/lib/permissions";
import type { PipelineOption } from "@/components/app/deal-form";
import type { Prisma } from "@/generated/prisma/client";

export const metadata = { title: "Deals" };

export default async function DealsPage(props: PageProps<"/deals">) {
  const me = await requireUser();
  const sp = await props.searchParams;
  const view = sp.view === "list" ? "list" : "board";
  const owner = typeof sp.owner === "string" && sp.owner ? sp.owner : undefined;
  const status = typeof sp.status === "string" && ["OPEN", "WON", "LOST"].includes(sp.status) ? (sp.status as "OPEN" | "WON" | "LOST") : undefined;
  const q = typeof sp.q === "string" ? sp.q.trim() : "";
  const stageFilter = typeof sp.stage === "string" && sp.stage ? sp.stage : undefined;
  const closedMonth = typeof sp.closed === "string" && /^\d{4}-\d{2}$/.test(sp.closed) ? sp.closed : undefined;

  const pipelines = await prisma.pipeline.findMany({
    where: { orgId: me.orgId },
    orderBy: { position: "asc" },
    include: { stages: { orderBy: { position: "asc" } } },
  });
  const current = pipelines.find((p) => p.id === sp.pipeline) ?? pipelines.find((p) => p.isDefault) ?? pipelines[0];
  if (!current) {
    return (
      <>
        <PageHeader title="Deals" />
        <EmptyState title="No pipelines" description="Ask an administrator to create a pipeline in Settings." />
      </>
    );
  }

  const closedRange = closedMonth
    ? (() => {
        const [y, m] = closedMonth.split("-").map(Number) as [number, number];
        const off = 5.5 * 3600 * 1000;
        return { gte: new Date(Date.UTC(y, m - 1, 1) - off), lt: new Date(Date.UTC(y, m, 1) - off) };
      })()
    : undefined;
  const where: Prisma.DealWhereInput = {
    orgId: me.orgId,
    // status-only drill-downs from the dashboard span all pipelines
    ...(sp.pipeline || !status ? { pipelineId: current.id } : {}),
    ...(owner ? { ownerId: owner } : {}),
    ...(status ? { status } : {}),
    ...(stageFilter ? { stageId: stageFilter } : {}),
    ...(closedRange ? { closedAt: closedRange } : {}),
    ...(q ? { OR: [{ title: { contains: q, mode: "insensitive" } }, { contact: { firstName: { contains: q, mode: "insensitive" } } }, { company: { name: { contains: q, mode: "insensitive" } } }] } : {}),
  };

  const [deals, users, tags, fieldDefs, views] = await Promise.all([
    prisma.deal.findMany({
      where,
      orderBy: [{ position: "asc" }, { updatedAt: "desc" }],
      include: {
        contact: { select: { id: true, firstName: true, lastName: true } },
        company: { select: { id: true, name: true } },
        owner: { select: { id: true, name: true, color: true } },
        stage: { select: { name: true } },
      },
    }),
    activeUsers(me.orgId),
    tagNames(me.orgId),
    getFieldDefs(me.orgId, "DEAL"),
    savedViewsFor(me.orgId, "DEAL", me.id),
  ]);

  const pipelineOptions: PipelineOption[] = pipelines.map((p) => ({
    id: p.id,
    name: p.name,
    isDefault: p.isDefault,
    stages: p.stages.map((s) => ({ id: s.id, name: s.name, kind: s.isWon ? "WON" : s.isLost ? "LOST" : "OPEN" })),
  }));

  const boardDeals: BoardDeal[] = deals.map((d) => ({
    id: d.id,
    title: d.title,
    stageId: d.stageId,
    status: d.status,
    amount: toNumber(d.amount),
    expectedCloseDate: d.expectedCloseDate ? toDateInput(d.expectedCloseDate) : null,
    contact: d.contact ? { id: d.contact.id, name: fullName(d.contact) } : null,
    company: d.company,
    owner: d.owner,
    position: d.position,
  }));

  const openDeals = deals.filter((d) => d.status === "OPEN");
  const openValue = openDeals.reduce((s, d) => s + toNumber(d.amount), 0);
  const params = (over: Record<string, string | undefined>) => {
    const u = new URLSearchParams();
    for (const [k, v] of Object.entries({ pipeline: current.id, view, owner, status, q, ...over })) if (v) u.set(k, v);
    return `/deals?${u}`;
  };

  const newDealDefaults = sp.new === "1" ? { contactId: typeof sp.contactId === "string" ? sp.contactId : undefined, companyId: typeof sp.companyId === "string" ? sp.companyId : undefined } : null;

  return (
    <div className="flex h-[calc(100vh-48px)] flex-col">
      <PageHeader
        title={
          <span className="flex items-center gap-3">
            Deals
            <PipelineSwitcher pipelines={pipelines.map((p) => ({ id: p.id, name: p.name }))} currentId={current.id} />
          </span>
        }
        subtitle={`${openDeals.length} open · ${formatINR(openValue)} in pipeline`}
        actions={
          <>
            <div className="flex rounded-md border border-line bg-white p-0.5 text-sm">
              <Link href={params({ view: "board" })} className={`rounded px-3 py-1 ${view === "board" ? "bg-navy text-white" : "text-ink-700"}`}>
                Board
              </Link>
              <Link href={params({ view: "list" })} className={`rounded px-3 py-1 ${view === "list" ? "bg-navy text-white" : "text-ink-700"}`}>
                List
              </Link>
            </div>
            <NewDealButton
              pipelines={pipelineOptions}
              users={users}
              fieldDefs={fieldDefs}
              tagSuggestions={tags}
              meId={me.id}
              defaultPipelineId={current.id}
              autoOpen={newDealDefaults}
            />
          </>
        }
      />
      <SavedViews entity="DEAL" views={views} meId={me.id} isAdmin={isAdmin(me)} exportHref="/api/export/deals" />
      <form className="mb-4 flex flex-wrap items-center gap-2">
        <input type="hidden" name="pipeline" value={current.id} />
        <input type="hidden" name="view" value={view} />
        <Input name="q" placeholder="Search deals…" defaultValue={q} className="w-64" />
        <Select name="owner" defaultValue={owner ?? ""} className="w-44">
          <option value="">All owners</option>
          {users.map((u) => (
            <option key={u.id} value={u.id}>
              {u.name}
            </option>
          ))}
        </Select>
        <Select name="status" defaultValue={status ?? ""} className="w-36">
          <option value="">All statuses</option>
          <option value="OPEN">Open</option>
          <option value="WON">Won</option>
          <option value="LOST">Lost</option>
        </Select>
        <Button type="submit" variant="secondary">
          Apply
        </Button>
      </form>

      {view === "board" ? (
        <DealBoard
          key={current.id + deals.length}
          pipelineId={current.id}
          stages={current.stages.map((s) => ({ id: s.id, name: s.name, color: s.color, kind: s.isWon ? "WON" : s.isLost ? "LOST" : "OPEN", probability: s.probability }))}
          deals={boardDeals}
        />
      ) : deals.length === 0 ? (
        <EmptyState title="No deals match" description="Change the filters or create a new deal." />
      ) : (
        <Card>
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-navy text-left text-[13px] font-semibold text-white">
                <th className="px-4 py-2.5">Deal</th>
                <th className="px-4 py-2.5">Stage</th>
                <th className="px-4 py-2.5">Contact</th>
                <th className="px-4 py-2.5">Company</th>
                <th className="px-4 py-2.5">Owner</th>
                <th className="px-4 py-2.5">Close date</th>
                <th className="px-4 py-2.5 text-right">Amount</th>
                <th className="px-4 py-2.5">Status</th>
              </tr>
            </thead>
            <tbody>
              {deals.map((d, i) => (
                <tr key={d.id} className={i % 2 ? "bg-white" : "bg-page"}>
                  <td className="px-4 py-2.5">
                    <Link href={`/deals/${d.id}`} className="font-medium text-primary hover:underline">
                      {d.title}
                    </Link>
                  </td>
                  <td className="px-4 py-2.5">{d.stage.name}</td>
                  <td className="px-4 py-2.5">{d.contact ? <Link href={`/contacts/${d.contact.id}`} className="hover:text-primary">{fullName(d.contact)}</Link> : "—"}</td>
                  <td className="px-4 py-2.5">{d.company ? <Link href={`/companies/${d.company.id}`} className="hover:text-primary">{d.company.name}</Link> : "—"}</td>
                  <td className="px-4 py-2.5">{d.owner ? <span className="flex items-center gap-1.5"><Avatar name={d.owner.name} color={d.owner.color} size={22} /><span className="text-xs">{d.owner.name}</span></span> : "—"}</td>
                  <td className="px-4 py-2.5">{formatDate(d.expectedCloseDate)}</td>
                  <td className="px-4 py-2.5 text-right tabular font-semibold">{formatINR(toNumber(d.amount))}</td>
                  <td className="px-4 py-2.5">
                    <Badge tone={d.status === "WON" ? "success" : d.status === "LOST" ? "danger" : "info"}>{d.status}</Badge>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </div>
  );
}
