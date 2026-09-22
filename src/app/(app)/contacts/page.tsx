import Link from "next/link";
import { requireUser } from "@/lib/auth";
import { prisma } from "@/lib/db";
import { fullName, relativeTime } from "@/lib/format";
import { activeUsers, tagNames } from "@/lib/queries";
import { getFieldDefs } from "@/lib/custom-fields";
import { Card, EmptyState, PageHeader } from "@/components/ui/card";
import { Avatar } from "@/components/ui/avatar";
import { TagChip } from "@/components/ui/badge";
import { ListToolbar, Pagination } from "@/components/app/list-toolbar";
import { SavedViews } from "@/components/app/saved-views";
import { savedViewsFor } from "@/lib/queries";
import { isAdmin } from "@/lib/permissions";
import { NewContactButton } from "./client";
import type { Prisma } from "@/generated/prisma/client";

export const metadata = { title: "Contacts" };
const PAGE_SIZE = 25;

export default async function ContactsPage(props: PageProps<"/contacts">) {
  const me = await requireUser();
  const sp = await props.searchParams;
  const q = typeof sp.q === "string" ? sp.q.trim() : "";
  const owner = typeof sp.owner === "string" && sp.owner ? sp.owner : undefined;
  const tag = typeof sp.tag === "string" && sp.tag ? sp.tag : undefined;
  const page = Math.max(1, Number(sp.page) || 1);

  const where: Prisma.ContactWhereInput = {
    orgId: me.orgId,
    ...(owner ? { ownerId: owner } : {}),
    ...(tag ? { tags: { has: tag } } : {}),
    ...(q
      ? {
          OR: [
            { firstName: { contains: q, mode: "insensitive" } },
            { lastName: { contains: q, mode: "insensitive" } },
            { email: { contains: q, mode: "insensitive" } },
            { phone: { contains: q } },
            { company: { name: { contains: q, mode: "insensitive" } } },
          ],
        }
      : {}),
  };

  const [rows, total, users, tags, fieldDefs, views] = await Promise.all([
    prisma.contact.findMany({
      where,
      orderBy: { updatedAt: "desc" },
      skip: (page - 1) * PAGE_SIZE,
      take: PAGE_SIZE,
      include: { company: { select: { id: true, name: true } }, owner: { select: { name: true, color: true } }, _count: { select: { deals: true } } },
    }),
    prisma.contact.count({ where }),
    activeUsers(me.orgId),
    tagNames(me.orgId),
    getFieldDefs(me.orgId, "CONTACT"),
    savedViewsFor(me.orgId, "CONTACT", me.id),
  ]);
  const pages = Math.ceil(total / PAGE_SIZE);

  return (
    <>
      <PageHeader
        title="Contacts"
        subtitle={`${total} contact${total === 1 ? "" : "s"}`}
        actions={<NewContactButton users={users} fieldDefs={fieldDefs} tagSuggestions={tags} meId={me.id} />}
      />
      <SavedViews entity="CONTACT" views={views} meId={me.id} isAdmin={isAdmin(me)} exportHref="/api/export/contacts" />
      <ListToolbar q={q} owners={users} ownerId={owner} tags={tags} tag={tag} />
      {rows.length === 0 ? (
        <EmptyState
          title={q || owner || tag ? "No contacts match" : "No contacts yet"}
          description={q || owner || tag ? "Try a different search or filter." : "Add your first contact or import a CSV from Settings."}
        />
      ) : (
        <Card>
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-navy text-left text-[13px] font-semibold text-white">
                <th className="px-4 py-2.5">Name</th>
                <th className="px-4 py-2.5">Company</th>
                <th className="px-4 py-2.5">Phone</th>
                <th className="px-4 py-2.5">Email</th>
                <th className="px-4 py-2.5">Tags</th>
                <th className="px-4 py-2.5">Owner</th>
                <th className="px-4 py-2.5 text-right">Deals</th>
                <th className="px-4 py-2.5">Updated</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((c, i) => (
                <tr key={c.id} className={i % 2 ? "bg-white" : "bg-page"}>
                  <td className="px-4 py-2.5">
                    <Link href={`/contacts/${c.id}`} className="font-medium text-primary hover:underline">
                      {fullName(c)}
                    </Link>
                    {c.jobTitle && <p className="text-xs text-ink-500">{c.jobTitle}</p>}
                  </td>
                  <td className="px-4 py-2.5">
                    {c.company ? (
                      <Link href={`/companies/${c.company.id}`} className="hover:text-primary">
                        {c.company.name}
                      </Link>
                    ) : (
                      <span className="text-ink-500">—</span>
                    )}
                  </td>
                  <td className="px-4 py-2.5 tabular">{c.phone ?? <span className="text-ink-500">—</span>}</td>
                  <td className="px-4 py-2.5">{c.email ?? <span className="text-ink-500">—</span>}</td>
                  <td className="px-4 py-2.5">
                    <div className="flex flex-wrap gap-1">
                      {c.tags.slice(0, 3).map((t) => (
                        <TagChip key={t} name={t} />
                      ))}
                      {c.tags.length > 3 && <span className="text-xs text-ink-500">+{c.tags.length - 3}</span>}
                    </div>
                  </td>
                  <td className="px-4 py-2.5">
                    {c.owner ? (
                      <span className="flex items-center gap-1.5">
                        <Avatar name={c.owner.name} color={c.owner.color} size={22} />
                        <span className="text-xs">{c.owner.name}</span>
                      </span>
                    ) : (
                      <span className="text-xs text-ink-500">Unassigned</span>
                    )}
                  </td>
                  <td className="px-4 py-2.5 text-right tabular">{c._count.deals}</td>
                  <td className="px-4 py-2.5 text-xs text-ink-500">{relativeTime(c.updatedAt)}</td>
                </tr>
              ))}
            </tbody>
          </table>
          <Pagination page={page} pages={pages} params={{ q, owner, tag }} />
        </Card>
      )}
    </>
  );
}
