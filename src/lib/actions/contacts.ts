"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { z } from "zod";
import { prisma } from "@/lib/db";
import { audit } from "@/lib/audit";
import { assert, canDeleteRecord, canEditRecord } from "@/lib/permissions";
import { normalizePhone, fullName } from "@/lib/format";
import { getFieldDefs, parseCustomFields } from "@/lib/custom-fields";
import { ensureTags, parseTags } from "@/lib/tags";
import { dedupeRules } from "@/lib/dedupe";
import { act, fail, optStr, str } from "./_helpers";

const contactSchema = z.object({
  firstName: z.string().trim().min(1, "First name is required").max(80),
  lastName: z.string().trim().max(80).optional().nullable(),
  email: z.string().trim().toLowerCase().email("Enter a valid email").optional().or(z.literal("")).nullable(),
  phone: z.string().trim().max(30).optional().nullable(),
  whatsappNumber: z.string().trim().max(30).optional().nullable(),
  jobTitle: z.string().trim().max(120).optional().nullable(),
  companyId: z.string().optional().nullable(),
  ownerId: z.string().optional().nullable(),
});

export type DuplicateHit = { id: string; name: string; email: string | null; phone: string | null; reason: string };

async function findContactDuplicates(orgId: string, input: { email?: string | null; phone?: string | null; whatsappNumber?: string | null }, excludeId?: string) {
  const phones = [normalizePhone(input.phone), normalizePhone(input.whatsappNumber)].filter(Boolean) as string[];
  const email = input.email || null;
  if (!email && phones.length === 0) return [] as DuplicateHit[];
  const rows = await prisma.contact.findMany({
    where: {
      orgId,
      ...(excludeId ? { id: { not: excludeId } } : {}),
      OR: [
        ...(email ? [{ email }] : []),
        ...(phones.length ? [{ phoneNormalized: { in: phones } }] : []),
      ],
    },
    select: { id: true, firstName: true, lastName: true, email: true, phone: true, phoneNormalized: true },
    take: 5,
  });
  return rows.map((r) => ({
    id: r.id,
    name: fullName(r),
    email: r.email,
    phone: r.phone,
    reason: email && r.email === email ? "Same email" : "Same phone number",
  }));
}

export async function checkContactDuplicates(input: { email?: string; phone?: string; whatsappNumber?: string }, excludeId?: string) {
  return act(async (user) => findContactDuplicates(user.orgId, input, excludeId));
}

async function readContactForm(orgId: string, formData: FormData) {
  const parsed = contactSchema.parse({
    firstName: str(formData, "firstName"),
    lastName: optStr(formData, "lastName"),
    email: optStr(formData, "email"),
    phone: optStr(formData, "phone"),
    whatsappNumber: optStr(formData, "whatsappNumber"),
    jobTitle: optStr(formData, "jobTitle"),
    companyId: optStr(formData, "companyId"),
    ownerId: optStr(formData, "ownerId"),
  });
  const defs = await getFieldDefs(orgId, "CONTACT");
  const customFields = parseCustomFields(defs, formData);
  const tags = parseTags(str(formData, "tags"));
  if (parsed.companyId) {
    const c = await prisma.company.findFirst({ where: { id: parsed.companyId, orgId }, select: { id: true } });
    if (!c) fail("Company not found.");
  }
  if (parsed.ownerId) {
    const u = await prisma.user.findFirst({ where: { id: parsed.ownerId, orgId, isActive: true }, select: { id: true } });
    if (!u) fail("Owner must be an active user.");
  }
  return {
    ...parsed,
    email: parsed.email || null,
    phoneNormalized: normalizePhone(parsed.phone) ?? normalizePhone(parsed.whatsappNumber),
    customFields,
    tags,
  };
}

export async function createContact(formData: FormData) {
  return act(async (user) => {
    const data = await readContactForm(user.orgId, formData);
    const force = formData.get("force") === "true";
    const rules = await dedupeRules(user.orgId);
    const dups = (await findContactDuplicates(user.orgId, data)).filter((d) => (d.reason === "Same email" ? rules.contactEmail !== "off" : rules.contactPhone !== "off"));
    const blocked = dups.find((d) => (d.reason === "Same email" ? rules.contactEmail === "block" : rules.contactPhone === "block"));
    if (blocked) fail(`${blocked.reason} as existing contact "${blocked.name}". Duplicate contacts are blocked by your workspace rules.`);
    if (!force && dups.length) return { duplicates: dups, id: null as string | null };
    const contact = await prisma.contact.create({
      data: { ...data, orgId: user.orgId, ownerId: data.ownerId ?? user.id },
    });
    await ensureTags(user.orgId, data.tags);
    await audit({ orgId: user.orgId, actorId: user.id, action: "create", entity: "CONTACT", entityId: contact.id, entityLabel: fullName(contact), after: data });
    revalidatePath("/contacts");
    return { duplicates: [] as DuplicateHit[], id: contact.id };
  });
}

export async function updateContact(id: string, formData: FormData) {
  return act(async (user) => {
    const existing = await prisma.contact.findFirst({ where: { id, orgId: user.orgId } });
    if (!existing) fail("Contact not found.");
    assert(await canEditRecord(user, "CONTACT", existing));
    const data = await readContactForm(user.orgId, formData);
    const contact = await prisma.contact.update({ where: { id }, data });
    await ensureTags(user.orgId, data.tags);
    await audit({ orgId: user.orgId, actorId: user.id, action: "update", entity: "CONTACT", entityId: id, entityLabel: fullName(contact), before: existing, after: data });
    revalidatePath(`/contacts/${id}`);
    revalidatePath("/contacts");
  });
}

export async function deleteContact(id: string) {
  return act(async (user) => {
    const existing = await prisma.contact.findFirst({ where: { id, orgId: user.orgId } });
    if (!existing) fail("Contact not found.");
    assert(canDeleteRecord(user, existing), "Only the owner or an admin can delete this contact.");
    await prisma.contact.delete({ where: { id } });
    await audit({ orgId: user.orgId, actorId: user.id, action: "delete", entity: "CONTACT", entityId: id, entityLabel: fullName(existing), before: existing });
    revalidatePath("/contacts");
    redirect("/contacts");
  });
}

export async function mergeContacts(targetId: string, sourceId: string) {
  return act(async (user) => {
    if (targetId === sourceId) fail("Pick two different contacts.");
    const [target, source] = await Promise.all([
      prisma.contact.findFirst({ where: { id: targetId, orgId: user.orgId } }),
      prisma.contact.findFirst({ where: { id: sourceId, orgId: user.orgId } }),
    ]);
    if (!target || !source) fail("Contact not found.");
    assert(await canEditRecord(user, "CONTACT", target));
    assert(canDeleteRecord(user, source), "You need delete rights on the contact being merged away.");

    const fill = <T,>(a: T | null, b: T | null) => a ?? b;
    const merged = {
      lastName: fill(target.lastName, source.lastName),
      email: fill(target.email, source.email),
      phone: fill(target.phone, source.phone),
      phoneNormalized: fill(target.phoneNormalized, source.phoneNormalized),
      whatsappNumber: fill(target.whatsappNumber, source.whatsappNumber),
      jobTitle: fill(target.jobTitle, source.jobTitle),
      companyId: fill(target.companyId, source.companyId),
      tags: Array.from(new Set([...target.tags, ...source.tags])),
      customFields: { ...(source.customFields as object), ...(target.customFields as object) },
    };

    await prisma.$transaction([
      prisma.deal.updateMany({ where: { contactId: source.id }, data: { contactId: target.id } }),
      prisma.activity.updateMany({ where: { contactId: source.id }, data: { contactId: target.id } }),
      prisma.note.updateMany({ where: { contactId: source.id }, data: { contactId: target.id } }),
      prisma.attachment.updateMany({ where: { contactId: source.id }, data: { contactId: target.id } }),
      prisma.contact.update({ where: { id: target.id }, data: merged }),
      prisma.contact.delete({ where: { id: source.id } }),
    ]);
    await audit({
      orgId: user.orgId,
      actorId: user.id,
      action: "merge",
      entity: "CONTACT",
      entityId: target.id,
      entityLabel: fullName(target),
      before: { mergedFrom: fullName(source), sourceId: source.id },
      after: merged,
    });
    revalidatePath("/contacts");
    revalidatePath(`/contacts/${target.id}`);
  });
}

export async function searchContacts(q: string) {
  return act(async (user) => {
    const rows = await prisma.contact.findMany({
      where: {
        orgId: user.orgId,
        OR: [
          { firstName: { contains: q, mode: "insensitive" } },
          { lastName: { contains: q, mode: "insensitive" } },
          { email: { contains: q, mode: "insensitive" } },
          { phone: { contains: q } },
        ],
      },
      select: { id: true, firstName: true, lastName: true, email: true, company: { select: { name: true } } },
      take: 8,
      orderBy: { updatedAt: "desc" },
    });
    return rows.map((r) => ({ id: r.id, label: fullName(r), sub: r.email ?? r.company?.name ?? "" }));
  });
}
