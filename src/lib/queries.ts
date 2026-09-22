import { prisma } from "./db";
import { formatDateTime } from "./format";
import type { EntityType } from "@/generated/prisma/client";

export function activeUsers(orgId: string) {
  return prisma.user.findMany({
    where: { orgId, isActive: true },
    orderBy: { name: "asc" },
    select: { id: true, name: true, color: true },
  });
}

export async function tagNames(orgId: string) {
  const tags = await prisma.tag.findMany({ where: { orgId }, orderBy: { name: "asc" }, select: { name: true } });
  return tags.map((t) => t.name);
}

export async function savedViewsFor(orgId: string, entity: EntityType, meId: string) {
  const views = await prisma.savedView.findMany({
    where: { orgId, entity, OR: [{ ownerId: meId }, { isShared: true }] },
    orderBy: [{ isShared: "asc" }, { name: "asc" }],
    include: { owner: { select: { name: true } } },
  });
  return views.map((v) => ({
    id: v.id,
    name: v.name,
    filters: (v.filters ?? {}) as Record<string, string>,
    isShared: v.isShared,
    ownerId: v.ownerId,
    ownerName: v.owner.name,
  }));
}

export type TimelineItem = {
  id: string;
  kind: "activity" | "note" | "audit" | "file";
  at: Date;
  title: string;
  detail?: string;
  meta?: string;
  href?: string;
};

// A single chronological feed for a contact, company or deal.
export async function recordTimeline(orgId: string, where: { contactId?: string; companyId?: string; dealId?: string }, auditEntity: { entity: string; entityId: string }) {
  const [activities, notes, files, audits] = await Promise.all([
    prisma.activity.findMany({
      where: { orgId, ...where },
      orderBy: { createdAt: "desc" },
      take: 100,
      include: { assignee: { select: { name: true } } },
    }),
    prisma.note.findMany({ where: { orgId, ...where }, orderBy: { createdAt: "desc" }, take: 100, include: { author: { select: { name: true } } } }),
    prisma.attachment.findMany({ where: { orgId, ...where }, orderBy: { createdAt: "desc" }, take: 50, include: { uploadedBy: { select: { name: true } } } }),
    prisma.auditLog.findMany({ where: { orgId, ...auditEntity }, orderBy: { createdAt: "desc" }, take: 100, include: { actor: { select: { name: true } } } }),
  ]);

  const items: TimelineItem[] = [
    ...activities.map((a) => ({
      id: `a-${a.id}`,
      kind: "activity" as const,
      at: a.dueAt ?? a.createdAt,
      title: `${a.type === "CALL" ? "Call" : a.type === "EVENT" ? "Meeting" : "Task"}: ${a.title}`,
      detail: a.description ?? undefined,
      meta: [a.status === "COMPLETED" ? "Completed" : a.status === "CANCELLED" ? "Cancelled" : a.dueAt ? `Due ${formatDateTime(a.dueAt)}` : "Open", a.assignee ? `· ${a.assignee.name}` : ""].join(" "),
      href: `/activities?focus=${a.id}`,
    })),
    ...notes.map((n) => ({ id: `n-${n.id}`, kind: "note" as const, at: n.createdAt, title: `Note by ${n.author.name}`, detail: n.body })),
    ...files.map((f) => ({ id: `f-${f.id}`, kind: "file" as const, at: f.createdAt, title: `File: ${f.filename}`, meta: `Uploaded by ${f.uploadedBy.name}`, href: `/api/files/${f.id}` })),
    ...audits
      .filter((l) => !["add_note", "upload_file", "delete_file"].includes(l.action))
      .map((l) => ({
        id: `l-${l.id}`,
        kind: "audit" as const,
        at: l.createdAt,
        title: `${humanAction(l.action)} by ${l.actor?.name ?? "System"}`,
        detail: summarizeChange(l.before, l.after),
      })),
  ];
  return items.sort((a, b) => b.at.getTime() - a.at.getTime());
}

function humanAction(a: string) {
  const map: Record<string, string> = {
    create: "Created",
    update: "Updated",
    delete: "Deleted",
    merge: "Merged",
    stage_change: "Stage changed",
    won: "Marked won",
    lost: "Marked lost",
    reopen: "Reopened",
    handoff: "Handed off",
  };
  return map[a] ?? a.replace(/_/g, " ");
}

function summarizeChange(before: unknown, after: unknown) {
  if (!before || !after || typeof before !== "object" || typeof after !== "object") return undefined;
  const b = before as Record<string, unknown>;
  const a = after as Record<string, unknown>;
  const changed = Object.keys(a).filter((k) => k in b && JSON.stringify(a[k]) !== JSON.stringify(b[k]) && !["updatedAt", "customFields", "phoneNormalized"].includes(k));
  if (changed.length === 0) return undefined;
  return changed
    .slice(0, 4)
    .map((k) => `${k}: ${fmt(b[k])} → ${fmt(a[k])}`)
    .join(" · ");
}

function fmt(v: unknown) {
  if (v === null || v === undefined || v === "") return "—";
  if (Array.isArray(v)) return v.join(", ") || "—";
  const s = typeof v === "string" ? v : JSON.stringify(v);
  return s.length > 30 ? s.slice(0, 30) + "…" : s;
}
