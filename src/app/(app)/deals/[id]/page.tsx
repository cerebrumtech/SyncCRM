import Link from "next/link";
import { notFound } from "next/navigation";
import { requireUser } from "@/lib/auth";
import { prisma } from "@/lib/db";
import { canDeleteRecord, canEditRecord, isAdmin } from "@/lib/permissions";
import { formatDate, formatINR, fullName, toDateInput, toNumber } from "@/lib/format";
import { activeUsers, recordTimeline, tagNames } from "@/lib/queries";
import { customValues, getFieldDefs } from "@/lib/custom-fields";
import { Card, CardBody, CardHeader, PageHeader } from "@/components/ui/card";
import { Avatar } from "@/components/ui/avatar";
import { Badge, TagChip } from "@/components/ui/badge";
import { Tabs } from "@/components/app/tabs";
import { Timeline } from "@/components/app/timeline";
import { FilesPanel, NotesPanel } from "@/components/app/record-panels";
import { LineItemsPanel } from "@/components/app/line-items";
import { ActivityList } from "@/components/app/activity-list";
import { ActivityQuickActions } from "@/components/app/activity-form";
import { activitiesFor } from "@/lib/activity-rows";
import { DealActions, StageStepper } from "./client";
import type { PipelineOption } from "@/components/app/deal-form";

export default async function DealPage(props: PageProps<"/deals/[id]">) {
  const me = await requireUser();
  const { id } = await props.params;
  const deal = await prisma.deal.findFirst({
    where: { id, orgId: me.orgId },
    include: {
      pipeline: { include: { stages: { orderBy: { position: "asc" } } } },
      stage: true,
      contact: { select: { id: true, firstName: true, lastName: true, email: true, phone: true } },
      company: { select: { id: true, name: true } },
      owner: { select: { id: true, name: true, color: true } },
      sourceDeal: { select: { id: true, title: true, pipeline: { select: { name: true } } } },
      handoffs: { select: { id: true, title: true, pipeline: { select: { name: true } }, stage: { select: { name: true } } } },
      notes: { orderBy: { createdAt: "desc" }, include: { author: { select: { id: true, name: true, color: true } } } },
      attachments: { orderBy: { createdAt: "desc" }, include: { uploadedBy: { select: { id: true, name: true } } } },
      lineItems: { orderBy: { position: "asc" } },
    },
  });
  if (!deal) notFound();

  const [users, tags, fieldDefs, timeline, canEdit, pipelines, activities] = await Promise.all([
    activeUsers(me.orgId),
    tagNames(me.orgId),
    getFieldDefs(me.orgId, "DEAL"),
    recordTimeline(me.orgId, { dealId: deal.id }, { entity: "DEAL", entityId: deal.id }),
    canEditRecord(me, "DEAL", deal),
    prisma.pipeline.findMany({ where: { orgId: me.orgId }, orderBy: { position: "asc" }, include: { stages: { orderBy: { position: "asc" } } } }),
    activitiesFor(me.orgId, { dealId: deal.id }, me),
  ]);
  const linked = {
    deal: { id: deal.id, label: deal.title },
    contact: deal.contact ? { id: deal.contact.id, label: fullName(deal.contact) } : null,
    company: deal.company ? { id: deal.company.id, label: deal.company.name } : null,
  };
  const cf = customValues(deal.customFields);
  const admin = isAdmin(me);
  const amount = toNumber(deal.amount);
  const pipelineOptions: PipelineOption[] = pipelines.map((p) => ({
    id: p.id,
    name: p.name,
    isDefault: p.isDefault,
    stages: p.stages.map((s) => ({ id: s.id, name: s.name, kind: s.isWon ? "WON" : s.isLost ? "LOST" : "OPEN" })),
  }));

  return (
    <>
      <div className="mb-4 text-xs text-ink-500">
        <Link href={`/deals?pipeline=${deal.pipelineId}`} className="hover:text-primary">
          Deals · {deal.pipeline.name}
        </Link>{" "}
        / {deal.title}
      </div>
      <PageHeader
        title={
          <span className="flex items-center gap-3">
            {deal.title}
            <Badge tone={deal.status === "WON" ? "success" : deal.status === "LOST" ? "danger" : "info"}>{deal.status}</Badge>
          </span>
        }
        subtitle={
          <span className="flex flex-wrap items-center gap-x-3 gap-y-1">
            <span className="text-lg font-semibold tabular text-navy">{formatINR(amount)}</span>
            {deal.expectedCloseDate && <span>Expected close {formatDate(deal.expectedCloseDate)}</span>}
            {deal.tags.map((t) => (
              <TagChip key={t} name={t} />
            ))}
          </span>
        }
        actions={
          <>
          <ActivityQuickActions users={users} meId={me.id} linked={linked} compact />
          <DealActions
            deal={{
              id: deal.id,
              title: deal.title,
              pipelineId: deal.pipelineId,
              stageId: deal.stageId,
              amount,
              amountIsManual: deal.amountIsManual,
              expectedCloseDate: toDateInput(deal.expectedCloseDate),
              contactId: deal.contactId,
              contactName: deal.contact ? fullName(deal.contact) : null,
              companyId: deal.companyId,
              companyName: deal.company?.name ?? null,
              ownerId: deal.ownerId,
              tags: deal.tags,
              customFields: cf,
            }}
            status={deal.status}
            canEdit={canEdit}
            canDelete={canDeleteRecord(me, deal)}
            pipelines={pipelineOptions}
            users={users}
            fieldDefs={fieldDefs}
            tagSuggestions={tags}
            meId={me.id}
          />
          </>
        }
      />

      <Card className="mb-5">
        <CardBody className="py-3">
          <StageStepper
            dealId={deal.id}
            currentStageId={deal.stageId}
            stages={deal.pipeline.stages.map((s) => ({ id: s.id, name: s.name, color: s.color, kind: s.isWon ? "WON" : s.isLost ? "LOST" : "OPEN" }))}
            canEdit={canEdit}
          />
          {deal.status === "LOST" && deal.lostReason && <p className="mt-2 text-sm text-danger-fg">Lost reason: {deal.lostReason}</p>}
        </CardBody>
      </Card>

      <div className="grid gap-5 lg:grid-cols-[340px_1fr]">
        <div className="space-y-5">
          <Card>
            <CardHeader title="Details" />
            <CardBody className="space-y-3 text-sm">
              <Row label="Contact" value={deal.contact ? <Link href={`/contacts/${deal.contact.id}`} className="text-primary hover:underline">{fullName(deal.contact)}</Link> : null} />
              <Row label="Company" value={deal.company ? <Link href={`/companies/${deal.company.id}`} className="text-primary hover:underline">{deal.company.name}</Link> : null} />
              <Row
                label="Owner"
                value={
                  deal.owner ? (
                    <span className="flex items-center gap-1.5">
                      <Avatar name={deal.owner.name} color={deal.owner.color} size={20} /> {deal.owner.name}
                    </span>
                  ) : (
                    "Unassigned"
                  )
                }
              />
              <Row label="Pipeline" value={`${deal.pipeline.name} · ${deal.stage.name}`} />
              <Row label="Win chance" value={`${deal.stage.probability}%`} />
              <Row label="Close date" value={formatDate(deal.expectedCloseDate)} />
              {deal.closedAt && <Row label="Closed on" value={formatDate(deal.closedAt)} />}
              <Row label="Created" value={formatDate(deal.createdAt)} />
              {deal.sourceDeal && (
                <Row
                  label="From"
                  value={
                    <Link href={`/deals/${deal.sourceDeal.id}`} className="text-primary hover:underline">
                      {deal.sourceDeal.title} <span className="text-ink-500">({deal.sourceDeal.pipeline.name})</span>
                    </Link>
                  }
                />
              )}
              {deal.handoffs.length > 0 && (
                <Row
                  label="Handed to"
                  value={
                    <ul className="space-y-0.5">
                      {deal.handoffs.map((h) => (
                        <li key={h.id}>
                          <Link href={`/deals/${h.id}`} className="text-primary hover:underline">
                            {h.pipeline.name} <span className="text-ink-500">· {h.stage.name}</span>
                          </Link>
                        </li>
                      ))}
                    </ul>
                  }
                />
              )}
              {fieldDefs.length > 0 && <hr className="border-line-100" />}
              {fieldDefs.map((d) => (
                <Row key={d.key} label={d.label} value={cf[d.key] === true ? "Yes" : cf[d.key] === false ? "No" : cf[d.key] == null ? null : String(cf[d.key])} />
              ))}
            </CardBody>
          </Card>
        </div>

        <Card>
          <CardBody>
            <Tabs
              tabs={[
                { key: "timeline", label: "Timeline", content: <Timeline items={timeline} /> },
                {
                  key: "activities",
                  label: "Activities",
                  count: activities.filter((a) => a.status === "OPEN").length,
                  content: <ActivityList items={activities} users={users} meId={me.id} showLinks={false} emptyText="No tasks, calls or meetings on this deal yet." />,
                },
                {
                  key: "items",
                  label: "Line items",
                  count: deal.lineItems.length,
                  content: (
                    <LineItemsPanel
                      dealId={deal.id}
                      amountIsManual={deal.amountIsManual}
                      canEdit={canEdit}
                      initial={deal.lineItems.map((li) => ({
                        productId: li.productId,
                        name: li.name,
                        quantity: toNumber(li.quantity),
                        unitPrice: toNumber(li.unitPrice),
                        discountPercent: toNumber(li.discountPercent),
                        taxRate: toNumber(li.taxRate),
                      }))}
                    />
                  ),
                },
                {
                  key: "notes",
                  label: "Notes",
                  count: deal.notes.length,
                  content: (
                    <NotesPanel
                      parent={{ entity: "DEAL", id: deal.id }}
                      notes={deal.notes.map((n) => ({ id: n.id, body: n.body, createdAt: n.createdAt.toISOString(), author: n.author }))}
                      meId={me.id}
                      isAdmin={admin}
                    />
                  ),
                },
                {
                  key: "files",
                  label: "Files",
                  count: deal.attachments.length,
                  content: (
                    <FilesPanel
                      parent={{ entity: "DEAL", id: deal.id }}
                      files={deal.attachments.map((f) => ({ id: f.id, filename: f.filename, mimeType: f.mimeType, size: f.size, createdAt: f.createdAt.toISOString(), uploadedBy: f.uploadedBy }))}
                      meId={me.id}
                      isAdmin={admin}
                    />
                  ),
                },
              ]}
            />
          </CardBody>
        </Card>
      </div>
    </>
  );
}

function Row({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex items-start gap-2">
      <span className="w-24 shrink-0 text-ink-500">{label}</span>
      <span className="min-w-0 flex-1 break-words text-ink">{value ?? <span className="text-ink-500">—</span>}</span>
    </div>
  );
}
