import { requireUser } from "@/lib/auth";
import { prisma } from "@/lib/db";
import { formatDateTime } from "@/lib/format";
import { Card } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Select, Input } from "@/components/ui/input";

export const metadata = { title: "Audit log" };

const PAGE_SIZE = 50;

export default async function AuditPage(props: PageProps<"/settings/audit">) {
  const me = await requireUser();
  const sp = await props.searchParams;
  const entity = typeof sp.entity === "string" && sp.entity ? sp.entity : undefined;
  const actorId = typeof sp.actor === "string" && sp.actor ? sp.actor : undefined;
  const q = typeof sp.q === "string" ? sp.q.trim() : "";
  const page = Math.max(1, Number(sp.page) || 1);

  const where = {
    orgId: me.orgId,
    ...(entity ? { entity } : {}),
    ...(actorId ? { actorId } : {}),
    ...(q ? { entityLabel: { contains: q, mode: "insensitive" as const } } : {}),
  };

  const [rows, total, users, entities] = await Promise.all([
    prisma.auditLog.findMany({
      where,
      orderBy: { createdAt: "desc" },
      skip: (page - 1) * PAGE_SIZE,
      take: PAGE_SIZE,
      include: { actor: { select: { name: true } } },
    }),
    prisma.auditLog.count({ where }),
    prisma.user.findMany({ where: { orgId: me.orgId }, select: { id: true, name: true }, orderBy: { name: "asc" } }),
    prisma.auditLog.findMany({ where: { orgId: me.orgId }, distinct: ["entity"], select: { entity: true } }),
  ]);
  const pages = Math.max(1, Math.ceil(total / PAGE_SIZE));

  return (
    <Card>
      <form className="flex flex-wrap items-end gap-2 border-b border-line-100 p-4">
        <div className="w-44">
          <Select name="entity" defaultValue={entity ?? ""}>
            <option value="">All entities</option>
            {entities.map((e) => (
              <option key={e.entity} value={e.entity}>
                {e.entity}
              </option>
            ))}
          </Select>
        </div>
        <div className="w-44">
          <Select name="actor" defaultValue={actorId ?? ""}>
            <option value="">All users</option>
            {users.map((u) => (
              <option key={u.id} value={u.id}>
                {u.name}
              </option>
            ))}
          </Select>
        </div>
        <div className="w-56">
          <Input name="q" placeholder="Search record name" defaultValue={q} />
        </div>
        <Button type="submit" variant="secondary">
          Filter
        </Button>
        <span className="ml-auto text-xs text-ink-500">{total} entries</span>
      </form>
      <table className="w-full text-sm">
        <thead>
          <tr className="bg-navy text-left text-[13px] font-semibold text-white">
            <th className="px-4 py-2.5">When</th>
            <th className="px-4 py-2.5">User</th>
            <th className="px-4 py-2.5">Action</th>
            <th className="px-4 py-2.5">Record</th>
            <th className="px-4 py-2.5">Change</th>
          </tr>
        </thead>
        <tbody>
          {rows.length === 0 && (
            <tr>
              <td colSpan={5} className="px-4 py-8 text-center text-ink-500">
                No audit entries match.
              </td>
            </tr>
          )}
          {rows.map((r, i) => (
            <tr key={r.id} className={i % 2 ? "bg-white" : "bg-page"}>
              <td className="whitespace-nowrap px-4 py-2 text-ink-700">{formatDateTime(r.createdAt)}</td>
              <td className="px-4 py-2">{r.actor?.name ?? "System"}</td>
              <td className="px-4 py-2">
                <Badge tone={r.action.includes("delete") || r.action === "deactivate" ? "danger" : r.action === "create" ? "success" : "info"}>
                  {r.action.replace(/_/g, " ")}
                </Badge>
              </td>
              <td className="px-4 py-2">
                <span className="text-xs text-ink-500">{r.entity}</span>{" "}
                <span className="font-medium">{r.entityLabel ?? r.entityId}</span>
              </td>
              <td className="max-w-md px-4 py-2 font-mono text-[11px] text-ink-700">
                <Diff before={r.before} after={r.after} />
              </td>
            </tr>
          ))}
        </tbody>
      </table>
      {pages > 1 && (
        <div className="flex items-center justify-between border-t border-line-100 px-4 py-2 text-xs text-ink-500">
          <span>
            Page {page} of {pages}
          </span>
          <div className="flex gap-1">
            {page > 1 && (
              <Button href={`?${new URLSearchParams({ ...(entity && { entity }), ...(actorId && { actor: actorId }), ...(q && { q }), page: String(page - 1) })}`} variant="secondary" size="sm">
                Previous
              </Button>
            )}
            {page < pages && (
              <Button href={`?${new URLSearchParams({ ...(entity && { entity }), ...(actorId && { actor: actorId }), ...(q && { q }), page: String(page + 1) })}`} variant="secondary" size="sm">
                Next
              </Button>
            )}
          </div>
        </div>
      )}
    </Card>
  );
}

function Diff({ before, after }: { before: unknown; after: unknown }) {
  if (!before && !after) return <span className="text-ink-500">—</span>;
  const b = (before ?? {}) as Record<string, unknown>;
  const a = (after ?? {}) as Record<string, unknown>;
  const keys = Array.from(new Set([...Object.keys(b), ...Object.keys(a)])).filter(
    (k) => JSON.stringify(b[k]) !== JSON.stringify(a[k]),
  );
  if (keys.length === 0) return <span className="text-ink-500">—</span>;
  return (
    <ul className="space-y-0.5">
      {keys.slice(0, 6).map((k) => (
        <li key={k} className="truncate">
          <span className="text-ink-500">{k}:</span>{" "}
          {k in b && <span className="text-danger line-through">{short(b[k])}</span>}{" "}
          {k in a && <span className="text-success-fg">{short(a[k])}</span>}
        </li>
      ))}
      {keys.length > 6 && <li className="text-ink-500">+{keys.length - 6} more</li>}
    </ul>
  );
}

function short(v: unknown) {
  const s = typeof v === "string" ? v : JSON.stringify(v);
  return s && s.length > 40 ? s.slice(0, 40) + "…" : (s ?? "");
}
