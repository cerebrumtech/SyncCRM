import { prisma } from "./db";
import { fullName, toDateInput, toDateTimeInput } from "./format";
import { isAdmin } from "./permissions";
import type { ActivityRow } from "@/components/app/activity-list";
import type { Prisma, Role } from "@/generated/prisma/client";

export const activityInclude = {
  assignee: { select: { id: true, name: true, color: true } },
  contact: { select: { id: true, firstName: true, lastName: true } },
  company: { select: { id: true, name: true } },
  deal: { select: { id: true, title: true } },
} satisfies Prisma.ActivityInclude;

type Loaded = Prisma.ActivityGetPayload<{ include: typeof activityInclude }>;

type Me = { id: string; orgId: string; role: Role };

export function toActivityRow(a: Loaded, me: Me): ActivityRow {
  const canEdit = isAdmin(me) || a.assigneeId === me.id || a.createdById === me.id || a.assigneeId === null;
  return {
    id: a.id,
    type: a.type,
    title: a.title,
    description: a.description,
    status: a.status,
    dueAtIso: a.dueAt?.toISOString() ?? null,
    dueAt: a.dueAt ? (a.allDay ? toDateInput(a.dueAt) : toDateTimeInput(a.dueAt)) : null,
    endAt: a.endAt ? toDateTimeInput(a.endAt) : null,
    allDay: a.allDay,
    location: a.location,
    recurrence: a.recurrence,
    recurrenceUntil: a.recurrenceUntil ? toDateInput(a.recurrenceUntil) : null,
    reminderMinutes: a.reminderMinutes,
    attendees: a.attendees,
    assigneeId: a.assigneeId,
    assignee: a.assignee,
    contact: a.contact ? { id: a.contact.id, label: fullName(a.contact) } : null,
    company: a.company ? { id: a.company.id, label: a.company.name } : null,
    deal: a.deal ? { id: a.deal.id, label: a.deal.title } : null,
    callDirection: a.callDirection,
    callDurationMin: a.callDurationSec != null ? Math.round(a.callDurationSec / 60) : null,
    callOutcome: a.callOutcome,
    canEdit,
  };
}

export async function activitiesFor(orgId: string, where: Prisma.ActivityWhereInput, me: Me, take = 200) {
  const rows = await prisma.activity.findMany({
    where: { orgId, ...where },
    orderBy: [{ status: "asc" }, { dueAt: "asc" }, { createdAt: "desc" }],
    include: activityInclude,
    take,
  });
  return rows.map((a) => toActivityRow(a, me));
}
