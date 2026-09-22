import Link from "next/link";
import { notFound } from "next/navigation";
import { Mail, Phone, MessageCircle, Building2, Briefcase } from "lucide-react";
import { requireUser } from "@/lib/auth";
import { prisma } from "@/lib/db";
import { canDeleteRecord, canEditRecord, isAdmin } from "@/lib/permissions";
import { formatDate, formatINR, fullName, toNumber } from "@/lib/format";
import { activeUsers, recordTimeline, tagNames } from "@/lib/queries";
import { customValues, getFieldDefs } from "@/lib/custom-fields";
import { Card, CardBody, CardHeader, PageHeader } from "@/components/ui/card";
import { Avatar } from "@/components/ui/avatar";
import { Badge, TagChip } from "@/components/ui/badge";
import { Tabs } from "@/components/app/tabs";
import { Timeline } from "@/components/app/timeline";
import { FilesPanel, NotesPanel } from "@/components/app/record-panels";
import { ActivityList } from "@/components/app/activity-list";
import { ActivityQuickActions } from "@/components/app/activity-form";
import { activitiesFor } from "@/lib/activity-rows";
import { ContactActions } from "../client";

export default async function ContactPage(props: PageProps<"/contacts/[id]">) {
  const me = await requireUser();
  const { id } = await props.params;
  const contact = await prisma.contact.findFirst({
    where: { id, orgId: me.orgId },
    include: {
      company: { select: { id: true, name: true } },
      owner: { select: { id: true, name: true, color: true } },
      deals: { orderBy: { updatedAt: "desc" }, include: { stage: true, pipeline: { select: { name: true } } } },
      notes: { orderBy: { createdAt: "desc" }, include: { author: { select: { id: true, name: true, color: true } } } },
      attachments: { orderBy: { createdAt: "desc" }, include: { uploadedBy: { select: { id: true, name: true } } } },
    },
  });
  if (!contact) notFound();

  const [users, tags, fieldDefs, timeline, canEdit, activities] = await Promise.all([
    activeUsers(me.orgId),
    tagNames(me.orgId),
    getFieldDefs(me.orgId, "CONTACT"),
    recordTimeline(me.orgId, { contactId: contact.id }, { entity: "CONTACT", entityId: contact.id }),
    canEditRecord(me, "CONTACT", contact),
    activitiesFor(me.orgId, { contactId: contact.id }, me),
  ]);
  const linked = { contact: { id: contact.id, label: fullName(contact) }, company: contact.company ? { id: contact.company.id, label: contact.company.name } : null };
  const cf = customValues(contact.customFields);
  const name = fullName(contact);
  const admin = isAdmin(me);

  return (
    <>
      <div className="mb-4 text-xs text-ink-500">
        <Link href="/contacts" className="hover:text-primary">
          Contacts
        </Link>{" "}
        / {name}
      </div>
      <PageHeader
        title={
          <span className="flex items-center gap-3">
            <Avatar name={name} color={contact.owner?.color ?? "#0068FF"} size={44} />
            {name}
          </span>
        }
        subtitle={
          <span className="flex flex-wrap items-center gap-x-3 gap-y-1">
            {contact.jobTitle && (
              <span className="flex items-center gap-1">
                <Briefcase size={13} /> {contact.jobTitle}
              </span>
            )}
            {contact.company && (
              <Link href={`/companies/${contact.company.id}`} className="flex items-center gap-1 hover:text-primary">
                <Building2 size={13} /> {contact.company.name}
              </Link>
            )}
            {contact.tags.map((t) => (
              <TagChip key={t} name={t} />
            ))}
          </span>
        }
        actions={
          <>
          <ActivityQuickActions users={users} meId={me.id} linked={linked} compact />
          <ContactActions
            contact={{
              id: contact.id,
              name,
              firstName: contact.firstName,
              lastName: contact.lastName,
              email: contact.email,
              phone: contact.phone,
              whatsappNumber: contact.whatsappNumber,
              jobTitle: contact.jobTitle,
              companyId: contact.companyId,
              companyName: contact.company?.name ?? null,
              ownerId: contact.ownerId,
              tags: contact.tags,
              customFields: cf,
            }}
            canEdit={canEdit}
            canDelete={canDeleteRecord(me, contact)}
            users={users}
            fieldDefs={fieldDefs}
            tagSuggestions={tags}
            meId={me.id}
          />
          </>
        }
      />

      <div className="grid gap-5 lg:grid-cols-[340px_1fr]">
        <div className="space-y-5">
          <Card>
            <CardHeader title="Details" />
            <CardBody className="space-y-3 text-sm">
              <Row icon={<Phone size={14} />} label="Phone" value={contact.phone ? <a href={`tel:${contact.phone}`} className="hover:text-primary">{contact.phone}</a> : null} />
              <Row icon={<MessageCircle size={14} />} label="WhatsApp" value={contact.whatsappNumber ?? contact.phone} />
              <Row icon={<Mail size={14} />} label="Email" value={contact.email ? <a href={`mailto:${contact.email}`} className="hover:text-primary">{contact.email}</a> : null} />
              <Row
                label="Owner"
                value={
                  contact.owner ? (
                    <span className="flex items-center gap-1.5">
                      <Avatar name={contact.owner.name} color={contact.owner.color} size={20} /> {contact.owner.name}
                    </span>
                  ) : (
                    "Unassigned"
                  )
                }
              />
              <Row label="Created" value={formatDate(contact.createdAt)} />
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
                { key: "overview", label: "Timeline", content: <Timeline items={timeline} /> },
                {
                  key: "activities",
                  label: "Activities",
                  count: activities.filter((a) => a.status === "OPEN").length,
                  content: <ActivityList items={activities} users={users} meId={me.id} showLinks={false} emptyText="No tasks, calls or meetings yet. Use the buttons above to add one." />,
                },
                {
                  key: "deals",
                  label: "Deals",
                  count: contact.deals.length,
                  content:
                    contact.deals.length === 0 ? (
                      <p className="text-sm text-ink-500">
                        No deals yet.{" "}
                        <Link href={`/deals?new=1&contactId=${contact.id}`} className="text-primary hover:underline">
                          Create a deal
                        </Link>
                      </p>
                    ) : (
                      <ul className="divide-y divide-line-100">
                        {contact.deals.map((d) => (
                          <li key={d.id} className="flex items-center justify-between py-2">
                            <div>
                              <Link href={`/deals/${d.id}`} className="font-medium text-primary hover:underline">
                                {d.title}
                              </Link>
                              <p className="text-xs text-ink-500">
                                {d.pipeline.name} · {d.stage.name} · Close {formatDate(d.expectedCloseDate)}
                              </p>
                            </div>
                            <div className="text-right">
                              <p className="tabular font-semibold">{formatINR(toNumber(d.amount))}</p>
                              <Badge tone={d.status === "WON" ? "success" : d.status === "LOST" ? "danger" : "info"}>{d.status}</Badge>
                            </div>
                          </li>
                        ))}
                      </ul>
                    ),
                },
                {
                  key: "notes",
                  label: "Notes",
                  count: contact.notes.length,
                  content: (
                    <NotesPanel
                      parent={{ entity: "CONTACT", id: contact.id }}
                      notes={contact.notes.map((n) => ({ id: n.id, body: n.body, createdAt: n.createdAt.toISOString(), author: n.author }))}
                      meId={me.id}
                      isAdmin={admin}
                    />
                  ),
                },
                {
                  key: "files",
                  label: "Files",
                  count: contact.attachments.length,
                  content: (
                    <FilesPanel
                      parent={{ entity: "CONTACT", id: contact.id }}
                      files={contact.attachments.map((f) => ({ id: f.id, filename: f.filename, mimeType: f.mimeType, size: f.size, createdAt: f.createdAt.toISOString(), uploadedBy: f.uploadedBy }))}
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

function Row({ icon, label, value }: { icon?: React.ReactNode; label: string; value: React.ReactNode }) {
  return (
    <div className="flex items-start gap-2">
      <span className="mt-0.5 w-4 text-ink-500">{icon}</span>
      <span className="w-20 shrink-0 text-ink-500">{label}</span>
      <span className="min-w-0 flex-1 break-words text-ink">{value ?? <span className="text-ink-500">—</span>}</span>
    </div>
  );
}
