"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { z } from "zod";
import { prisma } from "@/lib/db";
import { audit } from "@/lib/audit";
import { assert, canDeleteRecord, canEditRecord } from "@/lib/permissions";
import { getFieldDefs, parseCustomFields } from "@/lib/custom-fields";
import { ensureTags, parseTags } from "@/lib/tags";
import { dedupeRules } from "@/lib/dedupe";
import { act, fail, optStr, str } from "./_helpers";

const companySchema = z.object({
  name: z.string().trim().min(1, "Company name is required").max(160),
  industry: z.string().trim().max(80).optional().nullable(),
  website: z.string().trim().max(200).optional().nullable(),
  phone: z.string().trim().max(30).optional().nullable(),
  email: z.string().trim().toLowerCase().email("Enter a valid email").optional().or(z.literal("")).nullable(),
  addressLine: z.string().trim().max(200).optional().nullable(),
  city: z.string().trim().max(80).optional().nullable(),
  state: z.string().trim().max(80).optional().nullable(),
  postalCode: z.string().trim().max(20).optional().nullable(),
  country: z.string().trim().max(80).optional().nullable(),
  description: z.string().trim().max(2000).optional().nullable(),
  ownerId: z.string().optional().nullable(),
});

async function readCompanyForm(orgId: string, formData: FormData) {
  const parsed = companySchema.parse({
    name: str(formData, "name"),
    industry: optStr(formData, "industry"),
    website: optStr(formData, "website"),
    phone: optStr(formData, "phone"),
    email: optStr(formData, "email"),
    addressLine: optStr(formData, "addressLine"),
    city: optStr(formData, "city"),
    state: optStr(formData, "state"),
    postalCode: optStr(formData, "postalCode"),
    country: optStr(formData, "country") ?? "India",
    description: optStr(formData, "description"),
    ownerId: optStr(formData, "ownerId"),
  });
  const defs = await getFieldDefs(orgId, "COMPANY");
  const customFields = parseCustomFields(defs, formData);
  const tags = parseTags(str(formData, "tags"));
  if (parsed.ownerId) {
    const u = await prisma.user.findFirst({ where: { id: parsed.ownerId, orgId, isActive: true }, select: { id: true } });
    if (!u) fail("Owner must be an active user.");
  }
  return { ...parsed, email: parsed.email || null, customFields, tags };
}

export type CompanyDuplicate = { id: string; name: string; reason: string };

async function findCompanyDuplicates(orgId: string, name: string, excludeId?: string): Promise<CompanyDuplicate[]> {
  const rows = await prisma.company.findMany({
    where: { orgId, name: { equals: name, mode: "insensitive" }, ...(excludeId ? { id: { not: excludeId } } : {}) },
    select: { id: true, name: true },
    take: 5,
  });
  return rows.map((r) => ({ id: r.id, name: r.name, reason: "Same name" }));
}

export async function createCompany(formData: FormData) {
  return act(async (user) => {
    const data = await readCompanyForm(user.orgId, formData);
    const force = formData.get("force") === "true";
    const rules = await dedupeRules(user.orgId);
    const dups = rules.companyName === "off" ? [] : await findCompanyDuplicates(user.orgId, data.name);
    if (dups.length && rules.companyName === "block") fail(`A company named "${dups[0]!.name}" already exists. Duplicate companies are blocked by your workspace rules.`);
    if (!force && dups.length) return { duplicates: dups, id: null as string | null };
    const company = await prisma.company.create({
      data: { ...data, orgId: user.orgId, ownerId: data.ownerId ?? user.id },
    });
    await ensureTags(user.orgId, data.tags);
    await audit({ orgId: user.orgId, actorId: user.id, action: "create", entity: "COMPANY", entityId: company.id, entityLabel: company.name, after: data });
    revalidatePath("/companies");
    return { duplicates: [] as CompanyDuplicate[], id: company.id };
  });
}

export async function updateCompany(id: string, formData: FormData) {
  return act(async (user) => {
    const existing = await prisma.company.findFirst({ where: { id, orgId: user.orgId } });
    if (!existing) fail("Company not found.");
    assert(await canEditRecord(user, "COMPANY", existing));
    const data = await readCompanyForm(user.orgId, formData);
    await prisma.company.update({ where: { id }, data });
    await ensureTags(user.orgId, data.tags);
    await audit({ orgId: user.orgId, actorId: user.id, action: "update", entity: "COMPANY", entityId: id, entityLabel: data.name, before: existing, after: data });
    revalidatePath(`/companies/${id}`);
    revalidatePath("/companies");
  });
}

export async function deleteCompany(id: string) {
  return act(async (user) => {
    const existing = await prisma.company.findFirst({ where: { id, orgId: user.orgId } });
    if (!existing) fail("Company not found.");
    assert(canDeleteRecord(user, existing), "Only the owner or an admin can delete this company.");
    await prisma.company.delete({ where: { id } });
    await audit({ orgId: user.orgId, actorId: user.id, action: "delete", entity: "COMPANY", entityId: id, entityLabel: existing.name, before: existing });
    revalidatePath("/companies");
    redirect("/companies");
  });
}

export async function mergeCompanies(targetId: string, sourceId: string) {
  return act(async (user) => {
    if (targetId === sourceId) fail("Pick two different companies.");
    const [target, source] = await Promise.all([
      prisma.company.findFirst({ where: { id: targetId, orgId: user.orgId } }),
      prisma.company.findFirst({ where: { id: sourceId, orgId: user.orgId } }),
    ]);
    if (!target || !source) fail("Company not found.");
    assert(await canEditRecord(user, "COMPANY", target));
    assert(canDeleteRecord(user, source), "You need delete rights on the company being merged away.");
    const fill = <T,>(a: T | null, b: T | null) => a ?? b;
    const merged = {
      industry: fill(target.industry, source.industry),
      website: fill(target.website, source.website),
      phone: fill(target.phone, source.phone),
      email: fill(target.email, source.email),
      addressLine: fill(target.addressLine, source.addressLine),
      city: fill(target.city, source.city),
      state: fill(target.state, source.state),
      postalCode: fill(target.postalCode, source.postalCode),
      description: fill(target.description, source.description),
      tags: Array.from(new Set([...target.tags, ...source.tags])),
      customFields: { ...(source.customFields as object), ...(target.customFields as object) },
    };
    await prisma.$transaction([
      prisma.contact.updateMany({ where: { companyId: source.id }, data: { companyId: target.id } }),
      prisma.deal.updateMany({ where: { companyId: source.id }, data: { companyId: target.id } }),
      prisma.activity.updateMany({ where: { companyId: source.id }, data: { companyId: target.id } }),
      prisma.note.updateMany({ where: { companyId: source.id }, data: { companyId: target.id } }),
      prisma.attachment.updateMany({ where: { companyId: source.id }, data: { companyId: target.id } }),
      prisma.company.update({ where: { id: target.id }, data: merged }),
      prisma.company.delete({ where: { id: source.id } }),
    ]);
    await audit({ orgId: user.orgId, actorId: user.id, action: "merge", entity: "COMPANY", entityId: target.id, entityLabel: target.name, before: { mergedFrom: source.name, sourceId: source.id }, after: merged });
    revalidatePath("/companies");
    revalidatePath(`/companies/${target.id}`);
  });
}

export async function searchCompanies(q: string) {
  return act(async (user) => {
    const rows = await prisma.company.findMany({
      where: { orgId: user.orgId, name: { contains: q, mode: "insensitive" } },
      select: { id: true, name: true, city: true },
      take: 8,
      orderBy: { updatedAt: "desc" },
    });
    return rows.map((r) => ({ id: r.id, label: r.name, sub: r.city ?? "" }));
  });
}
