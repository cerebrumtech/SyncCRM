"use server";

import { revalidatePath } from "next/cache";
import { z } from "zod";
import { prisma } from "@/lib/db";
import { audit } from "@/lib/audit";
import { assert, isAdmin } from "@/lib/permissions";
import { fromDateInput, fromDateTimeInput } from "@/lib/format";
import { act, fail, optStr, str } from "./_helpers";
import type { Activity } from "@/generated/prisma/client";

const activitySchema = z.object({
  type: z.enum(["TASK", "CALL", "EVENT"]),
  title: z.string().trim().min(1, "Title is required").max(160),
  description: z.string().trim().max(4000).optional().nullable(),
  dueAt: z.string().optional().nullable(),
  endAt: z.string().optional().nullable(),
  allDay: z.boolean(),
  location: z.string().trim().max(200).optional().nullable(),
  recurrence: z.enum(["NONE", "DAILY", "WEEKLY", "MONTHLY"]),
  recurrenceUntil: z.string().optional().nullable(),
  reminderMinutes: z.coerce.number().int().min(0).max(60 * 24 * 30).optional().nullable(),
  attendees: z.string().optional().nullable(),
  assigneeId: z.string().optional().nullable(),
  contactId: z.string().optional().nullable(),
  companyId: z.string().optional().nullable(),
  dealId: z.string().optional().nullable(),
  callDirection: z.enum(["INBOUND", "OUTBOUND"]).optional().nullable(),
  callDurationMin: z.coerce.number().min(0).max(1440).optional().nullable(),
  callOutcome: z.string().trim().max(200).optional().nullable(),
  markCompleted: z.boolean(),
});

async function readForm(orgId: string, formData: FormData) {
  const p = activitySchema.parse({
    type: str(formData, "type") || "TASK",
    title: str(formData, "title"),
    description: optStr(formData, "description"),
    dueAt: optStr(formData, "dueAt"),
    endAt: optStr(formData, "endAt"),
    allDay: formData.get("allDay") === "on",
    location: optStr(formData, "location"),
    recurrence: str(formData, "recurrence") || "NONE",
    recurrenceUntil: optStr(formData, "recurrenceUntil"),
    reminderMinutes: optStr(formData, "reminderMinutes"),
    attendees: optStr(formData, "attendees"),
    assigneeId: optStr(formData, "assigneeId"),
    contactId: optStr(formData, "contactId"),
    companyId: optStr(formData, "companyId"),
    dealId: optStr(formData, "dealId"),
    callDirection: optStr(formData, "callDirection"),
    callDurationMin: optStr(formData, "callDurationMin"),
    callOutcome: optStr(formData, "callOutcome"),
    markCompleted: formData.get("markCompleted") === "on",
  });

  const dueAt = p.allDay ? fromDateInput(p.dueAt) : fromDateTimeInput(p.dueAt) ?? fromDateInput(p.dueAt);
  const endAt = p.allDay ? null : fromDateTimeInput(p.endAt);
  if (p.type === "EVENT" && !dueAt) fail("Events need a start date and time.");
  if (endAt && dueAt && endAt < dueAt) fail("End time must be after the start time.");

  const [assignee, contact, company, deal] = await Promise.all([
    p.assigneeId ? prisma.user.findFirst({ where: { id: p.assigneeId, orgId, isActive: true }, select: { id: true } }) : null,
    p.contactId ? prisma.contact.findFirst({ where: { id: p.contactId, orgId }, select: { id: true, companyId: true } }) : null,
    p.companyId ? prisma.company.findFirst({ where: { id: p.companyId, orgId }, select: { id: true } }) : null,
    p.dealId ? prisma.deal.findFirst({ where: { id: p.dealId, orgId }, select: { id: true, contactId: true, companyId: true } }) : null,
  ]);
  if (p.assigneeId && !assignee) fail("Assignee must be an active user.");
  if (p.contactId && !contact) fail("Contact not found.");
  if (p.companyId && !company) fail("Company not found.");
  if (p.dealId && !deal) fail("Deal not found.");

  const attendees = (p.attendees ?? "")
    .split(/[,\s]+/)
    .map((s) => s.trim())
    .filter((s) => /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(s))
    .slice(0, 50);

  return {
    type: p.type,
    title: p.title,
    description: p.description ?? null,
    dueAt,
    endAt,
    allDay: p.allDay,
    location: p.location ?? null,
    recurrence: p.recurrence,
    recurrenceUntil: fromDateInput(p.recurrenceUntil),
    reminderMinutes: p.reminderMinutes ?? null,
    attendees,
    assigneeId: p.assigneeId ?? null,
    contactId: p.contactId ?? null,
    companyId: p.companyId ?? deal?.companyId ?? contact?.companyId ?? null,
    dealId: p.dealId ?? null,
    callDirection: p.type === "CALL" ? (p.callDirection ?? "OUTBOUND") : null,
    callDurationSec: p.type === "CALL" && p.callDurationMin != null ? Math.round(p.callDurationMin * 60) : null,
    callOutcome: p.type === "CALL" ? (p.callOutcome ?? null) : null,
    markCompleted: p.markCompleted,
  };
}

function paths(a: { contactId: string | null; companyId: string | null; dealId: string | null }) {
  revalidatePath("/activities");
  revalidatePath("/dashboard");
  if (a.contactId) revalidatePath(`/contacts/${a.contactId}`);
  if (a.companyId) revalidatePath(`/companies/${a.companyId}`);
  if (a.dealId) revalidatePath(`/deals/${a.dealId}`);
}

async function canTouch(user: { id: string; orgId: string; role: "OWNER" | "ADMIN" | "MEMBER" }, a: Activity) {
  return a.orgId === user.orgId && (isAdmin(user) || a.assigneeId === user.id || a.createdById === user.id || a.assigneeId === null);
}

export async function createActivity(formData: FormData) {
  return act(async (user) => {
    const { markCompleted, ...data } = await readForm(user.orgId, formData);
    const completed = markCompleted || (data.type === "CALL" && formData.get("logged") === "on");
    const activity = await prisma.activity.create({
      data: {
        ...data,
        orgId: user.orgId,
        assigneeId: data.assigneeId ?? user.id,
        createdById: user.id,
        status: completed ? "COMPLETED" : "OPEN",
        completedAt: completed ? new Date() : null,
      },
    });
    await audit({ orgId: user.orgId, actorId: user.id, action: data.type === "CALL" ? "log_call" : "create", entity: "Activity", entityId: activity.id, entityLabel: activity.title, after: data });

    // Optional follow-up task after a logged call
    const followUp = optStr(formData, "followUpTitle");
    if (data.type === "CALL" && followUp) {
      const due = fromDateTimeInput(optStr(formData, "followUpDueAt")) ?? new Date(Date.now() + 86_400_000);
      await prisma.activity.create({
        data: {
          orgId: user.orgId,
          type: "TASK",
          title: followUp,
          dueAt: due,
          assigneeId: data.assigneeId ?? user.id,
          createdById: user.id,
          contactId: data.contactId,
          companyId: data.companyId,
          dealId: data.dealId,
          reminderMinutes: 30,
        },
      });
    }
    paths(data);
    return { id: activity.id };
  });
}

export async function updateActivity(id: string, formData: FormData) {
  return act(async (user) => {
    const existing = await prisma.activity.findFirst({ where: { id, orgId: user.orgId } });
    if (!existing) fail("Activity not found.");
    assert(await canTouch(user, existing), "You can only edit activities assigned to you.");
    const { markCompleted, ...data } = await readForm(user.orgId, formData);
    const activity = await prisma.activity.update({
      where: { id },
      data: {
        ...data,
        ...(markCompleted && existing.status !== "COMPLETED" ? { status: "COMPLETED", completedAt: new Date() } : {}),
        ...(!markCompleted && existing.status === "COMPLETED" ? { status: "OPEN", completedAt: null } : {}),
      },
    });
    await audit({ orgId: user.orgId, actorId: user.id, action: "update", entity: "Activity", entityId: id, entityLabel: activity.title, before: existing, after: data });
    paths(existing);
    paths(data);
  });
}

function nextOccurrence(a: Activity): Date | null {
  if (a.recurrence === "NONE" || !a.dueAt) return null;
  const d = new Date(a.dueAt);
  if (a.recurrence === "DAILY") d.setUTCDate(d.getUTCDate() + 1);
  if (a.recurrence === "WEEKLY") d.setUTCDate(d.getUTCDate() + 7);
  if (a.recurrence === "MONTHLY") d.setUTCMonth(d.getUTCMonth() + 1);
  if (a.recurrenceUntil && d > new Date(a.recurrenceUntil.getTime() + 86_399_000)) return null;
  return d;
}

export async function setActivityStatus(id: string, status: "OPEN" | "COMPLETED" | "CANCELLED") {
  return act(async (user) => {
    const a = await prisma.activity.findFirst({ where: { id, orgId: user.orgId } });
    if (!a) fail("Activity not found.");
    assert(await canTouch(user, a), "You can only update activities assigned to you.");
    await prisma.activity.update({ where: { id }, data: { status, completedAt: status === "COMPLETED" ? new Date() : null } });
    if (status === "COMPLETED" && a.status !== "COMPLETED") {
      const next = nextOccurrence(a);
      if (next) {
        const duration = a.endAt && a.dueAt ? a.endAt.getTime() - a.dueAt.getTime() : null;
        await prisma.activity.create({
          data: {
            orgId: a.orgId,
            type: a.type,
            title: a.title,
            description: a.description,
            dueAt: next,
            endAt: duration ? new Date(next.getTime() + duration) : null,
            allDay: a.allDay,
            location: a.location,
            recurrence: a.recurrence,
            recurrenceUntil: a.recurrenceUntil,
            reminderMinutes: a.reminderMinutes,
            attendees: a.attendees,
            assigneeId: a.assigneeId,
            contactId: a.contactId,
            companyId: a.companyId,
            dealId: a.dealId,
            createdById: a.createdById,
          },
        });
      }
    }
    await audit({ orgId: user.orgId, actorId: user.id, action: status.toLowerCase(), entity: "Activity", entityId: id, entityLabel: a.title });
    paths(a);
  });
}

export async function deleteActivity(id: string) {
  return act(async (user) => {
    const a = await prisma.activity.findFirst({ where: { id, orgId: user.orgId } });
    if (!a) fail("Activity not found.");
    assert(isAdmin(user) || a.createdById === user.id || a.assigneeId === user.id, "You can only delete your own activities.");
    await prisma.activity.delete({ where: { id } });
    await audit({ orgId: user.orgId, actorId: user.id, action: "delete", entity: "Activity", entityId: id, entityLabel: a.title, before: a });
    paths(a);
  });
}
