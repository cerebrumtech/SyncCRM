import Link from "next/link";
import { requireUser } from "@/lib/auth";
import { prisma } from "@/lib/db";
import { relativeTime } from "@/lib/format";
import { activeUsers, tagNames } from "@/lib/queries";
import { getFieldDefs } from "@/lib/custom-fields";
import { Card, EmptyState, PageHeader } from "@/components/ui/card";
import { Avatar } from "@/components/ui/avatar";
import { TagChip } from "@/components/ui/badge";
import { ListToolbar, Pagination } from "@/components/app/list-toolbar";
import { SavedViews } from "@/components/app/saved-views";
import { savedViewsFor } from "@/lib/queries";
import { isAdmin } from "@/lib/permissions";
import { NewCompanyButton } from "./client";
import type { Prisma } from "@/generated/prisma/client";

export const metadata = { title: "Companies" };
const PAGE_SIZE = 25;

export default async function CompaniesPage(props: PageProps<"/companies">) {
  const me = await requireUser();
  const sp = await props.searchParams;
  const q = typeof sp.q === "string" ? sp.q.trim() : "";
  const owner = typeof sp.owner === "string" && sp.owner ? sp.owner : undefined;
  const tag = typeof sp.tag === "string" && sp.tag ? sp.tag : undefined;
  const page = Math.max(1, Number(sp.page) || 1);

  const where: Prisma.CompanyWhereInput = {
    orgId: me.orgId,
    ...(owner ? { ownerId: owner } : {}),
    ...(tag ? { tags: { has: tag } } : {}),
    ...(q
      ? {
          OR: [
            { name: { contains: q, mode: "insensitive" } },
            { city: { contains: q, mode: "insensitive" } },
            { industry: { contains: q, mode: "insensitive" } },
            { email: { contains: q, mode: "insensitive" } },
          ],
        }
      : {}),
  };

  const [rows, total, users, tags, fieldDefs, views] = await Promise.all([
    prisma.company.findMany({
      where,
      orderBy: { updatedAt: "desc" },
      skip: (page - 1) * PAGE_SIZE,
      take: PAGE_SIZE,
      include: { owner: { select: { name: true, color: true } }, _count: { select: { contacts: true, deals: true } } },
    }),
    prisma.company.count({ where }),
    activeUsers(me.orgId),
    tagNames(me.orgId),
    getFieldDefs(me.orgId, "COMPANY"),
    savedViewsFor(me.orgId, "COMPANY", me.id),
  ]);
  const pages = Math.ceil(total / PAGE_SIZE);

  return (
    <>
      <PageHeader
        title="Companies"
        subtitle={`${total} compan${total === 1 ? "y" : "ies"}`}
        actions={<NewCompanyButton users={users} fieldDefs={fieldDefs} tagSuggestions={tags} meId={me.id} />}
      />
      <SavedViews entity="COMPANY" views={views} meId={me.id} isAdmin={isAdmin(me)} exportHref="/api/export/companies" />
      <ListToolbar q={q} owners={users} ownerId={owner} tags={tags} tag={tag} />
      {rows.length === 0 ? (
        <EmptyState title={q || owner || tag ? "No companies match" : "No companies yet"} description="Add a company to group its contacts and deals." />
      ) : (
        <Card>
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-navy text-left text-[13px] font-semibold text-white">
                <th className="px-4 py-2.5">Company</th>
                <th className="px-4 py-2.5">Industry</th>
                <th className="px-4 py-2.5">Location</th>
                <th className="px-4 py-2.5">Tags</th>
                <th className="px-4 py-2.5">Owner</th>
                <th className="px-4 py-2.5 text-right">Contacts</th>
                <th className="px-4 py-2.5 text-right">Deals</th>
                <th className="px-4 py-2.5">Updated</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((c, i) => (
                <tr key={c.id} className={i % 2 ? "bg-white" : "bg-page"}>
                  <td className="px-4 py-2.5">
                    <Link href={`/companies/${c.id}`} className="font-medium text-primary hover:underline">
                      {c.name}
                    </Link>
                    {c.website && <p className="text-xs text-ink-500">{c.website}</p>}
                  </td>
                  <td className="px-4 py-2.5">{c.industry ?? <span className="text-ink-500">—</span>}</td>
                  <td className="px-4 py-2.5">{[c.city, c.state].filter(Boolean).join(", ") || <span className="text-ink-500">—</span>}</td>
                  <td className="px-4 py-2.5">
                    <div className="flex flex-wrap gap-1">
                      {c.tags.slice(0, 3).map((t) => (
                        <TagChip key={t} name={t} />
                      ))}
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
                  <td className="px-4 py-2.5 text-right tabular">{c._count.contacts}</td>
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
