import Link from "next/link";
import { notFound } from "next/navigation";
import { Mail, Phone, Globe, MapPin, Factory } from "lucide-react";
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
import { CompanyActions } from "../client";
import { NewContactButton } from "../../contacts/client";

export default async function CompanyPage(props: PageProps<"/companies/[id]">) {
  const me = await requireUser();
  const { id } = await props.params;
  const company = await prisma.company.findFirst({
    where: { id, orgId: me.orgId },
    include: {
      owner: { select: { id: true, name: true, color: true } },
      contacts: { orderBy: { firstName: "asc" }, select: { id: true, firstName: true, lastName: true, email: true, phone: true, jobTitle: true } },
      deals: { orderBy: { updatedAt: "desc" }, include: { stage: true, pipeline: { select: { name: true } }, contact: { select: { firstName: true, lastName: true } } } },
      notes: { orderBy: { createdAt: "desc" }, include: { author: { select: { id: true, name: true, color: true } } } },
      attachments: { orderBy: { createdAt: "desc" }, include: { uploadedBy: { select: { id: true, name: true } } } },
    },
  });
  if (!company) notFound();

  const [users, tags, fieldDefs, contactFieldDefs, timeline, canEdit, activities] = await Promise.all([
    activeUsers(me.orgId),
    tagNames(me.orgId),
    getFieldDefs(me.orgId, "COMPANY"),
    getFieldDefs(me.orgId, "CONTACT"),
    recordTimeline(me.orgId, { companyId: company.id }, { entity: "COMPANY", entityId: company.id }),
    canEditRecord(me, "COMPANY", company),
    activitiesFor(me.orgId, { companyId: company.id }, me),
  ]);
  const cf = customValues(company.customFields);
  const admin = isAdmin(me);
  const address = [company.addressLine, company.city, company.state, company.postalCode, company.country].filter(Boolean).join(", ");
  const openValue = company.deals.filter((d) => d.status === "OPEN").reduce((s, d) => s + toNumber(d.amount), 0);

  return (
    <>
      <div className="mb-4 text-xs text-ink-500">
        <Link href="/companies" className="hover:text-primary">
          Companies
        </Link>{" "}
        / {company.name}
      </div>
      <PageHeader
        title={company.name}
        subtitle={
          <span className="flex flex-wrap items-center gap-x-3 gap-y-1">
            {company.industry && (
              <span className="flex items-center gap-1">
                <Factory size={13} /> {company.industry}
              </span>
            )}
            {company.tags.map((t) => (
              <TagChip key={t} name={t} />
            ))}
          </span>
        }
        actions={
          <>
            <ActivityQuickActions users={users} meId={me.id} linked={{ company: { id: company.id, label: company.name } }} compact />
            <CompanyActions
              company={{ ...company, customFields: cf }}
              canEdit={canEdit}
              canDelete={canDeleteRecord(me, company)}
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
              <Row icon={<Phone size={14} />} label="Phone" value={company.phone} />
              <Row icon={<Mail size={14} />} label="Email" value={company.email} />
              <Row icon={<Globe size={14} />} label="Website" value={company.website ? <a href={company.website.startsWith("http") ? company.website : `https://${company.website}`} target="_blank" className="hover:text-primary">{company.website}</a> : null} />
              <Row icon={<MapPin size={14} />} label="Address" value={address || null} />
              <Row
                label="Owner"
                value={
                  company.owner ? (
                    <span className="flex items-center gap-1.5">
                      <Avatar name={company.owner.name} color={company.owner.color} size={20} /> {company.owner.name}
                    </span>
                  ) : (
                    "Unassigned"
                  )
                }
              />
              <Row label="Open pipeline" value={<span className="tabular font-semibold">{formatINR(openValue)}</span>} />
              <Row label="Created" value={formatDate(company.createdAt)} />
              {company.description && (
                <>
                  <hr className="border-line-100" />
                  <p className="whitespace-pre-wrap text-ink-700">{company.description}</p>
                </>
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
                  content: <ActivityList items={activities} users={users} meId={me.id} emptyText="No tasks, calls or meetings yet." />,
                },
                {
                  key: "contacts",
                  label: "Contacts",
                  count: company.contacts.length,
                  content: (
                    <div>
                      <div className="mb-3 flex justify-end">
                        <NewContactButton
                          users={users}
                          fieldDefs={contactFieldDefs}
                          tagSuggestions={tags}
                          meId={me.id}
                          defaultCompany={{ id: company.id, label: company.name }}
                          label="Add contact"
                        />
                      </div>
                      {company.contacts.length === 0 ? (
                        <p className="text-sm text-ink-500">No contacts linked yet.</p>
                      ) : (
                        <ul className="divide-y divide-line-100">
                          {company.contacts.map((c) => (
                            <li key={c.id} className="flex items-center justify-between py-2">
                              <div>
                                <Link href={`/contacts/${c.id}`} className="font-medium text-primary hover:underline">
                                  {fullName(c)}
                                </Link>
                                <p className="text-xs text-ink-500">{[c.jobTitle, c.email, c.phone].filter(Boolean).join(" · ")}</p>
                              </div>
                            </li>
                          ))}
                        </ul>
                      )}
                    </div>
                  ),
                },
                {
                  key: "deals",
                  label: "Deals",
                  count: company.deals.length,
                  content:
                    company.deals.length === 0 ? (
                      <p className="text-sm text-ink-500">
                        No deals yet.{" "}
                        <Link href={`/deals?new=1&companyId=${company.id}`} className="text-primary hover:underline">
                          Create a deal
                        </Link>
                      </p>
                    ) : (
                      <ul className="divide-y divide-line-100">
                        {company.deals.map((d) => (
                          <li key={d.id} className="flex items-center justify-between py-2">
                            <div>
                              <Link href={`/deals/${d.id}`} className="font-medium text-primary hover:underline">
                                {d.title}
                              </Link>
                              <p className="text-xs text-ink-500">
                                {d.pipeline.name} · {d.stage.name} {d.contact && `· ${fullName(d.contact)}`}
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
                  count: company.notes.length,
                  content: (
                    <NotesPanel
                      parent={{ entity: "COMPANY", id: company.id }}
                      notes={company.notes.map((n) => ({ id: n.id, body: n.body, createdAt: n.createdAt.toISOString(), author: n.author }))}
                      meId={me.id}
                      isAdmin={admin}
                    />
                  ),
                },
                {
                  key: "files",
                  label: "Files",
                  count: company.attachments.length,
                  content: (
                    <FilesPanel
                      parent={{ entity: "COMPANY", id: company.id }}
                      files={company.attachments.map((f) => ({ id: f.id, filename: f.filename, mimeType: f.mimeType, size: f.size, createdAt: f.createdAt.toISOString(), uploadedBy: f.uploadedBy }))}
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
      <span className="w-24 shrink-0 text-ink-500">{label}</span>
      <span className="min-w-0 flex-1 break-words text-ink">{value ?? <span className="text-ink-500">—</span>}</span>
    </div>
  );
}
