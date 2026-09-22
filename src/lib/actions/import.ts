"use server";

import { revalidatePath } from "next/cache";
import { z } from "zod";
import { prisma } from "@/lib/db";
import { audit } from "@/lib/audit";
import { assert, canManageSettings } from "@/lib/permissions";
import { normalizePhone } from "@/lib/format";
import { getFieldDefs, type CustomValues } from "@/lib/custom-fields";
import { ensureTags, parseTags } from "@/lib/tags";
import { act, fail } from "./_helpers";

export type ImportEntity = "CONTACT" | "COMPANY";
export type ImportMapping = Record<string, string>; // csv column index (as string) -> target field key
export type ImportOptions = { onDuplicate: "skip" | "update" | "create" };
export type ImportSummary = { created: number; updated: number; skipped: number; errors: string[] };

const CONTACT_FIELDS = ["firstName", "lastName", "email", "phone", "whatsappNumber", "jobTitle", "companyName", "ownerEmail", "tags"] as const;
const COMPANY_FIELDS = ["name", "industry", "website", "phone", "email", "addressLine", "city", "state", "postalCode", "country", "description", "ownerEmail", "tags"] as const;

export async function importTargets(entity: ImportEntity) {
  return act(async (user) => {
    const defs = await getFieldDefs(user.orgId, entity);
    const builtIn = (entity === "CONTACT" ? CONTACT_FIELDS : COMPANY_FIELDS).map((k) => ({ key: k, label: labelFor(k) }));
    return [...builtIn, ...defs.map((d) => ({ key: `cf_${d.key}`, label: `${d.label} (custom)` }))];
  });
}

function labelFor(k: string) {
  const map: Record<string, string> = {
    firstName: "First name",
    lastName: "Last name",
    email: "Email",
    phone: "Phone",
    whatsappNumber: "WhatsApp number",
    jobTitle: "Job title",
    companyName: "Company (by name)",
    ownerEmail: "Owner (by email)",
    tags: "Tags (; separated)",
    name: "Company name",
    industry: "Industry",
    website: "Website",
    addressLine: "Address",
    city: "City",
    state: "State",
    postalCode: "PIN code",
    country: "Country",
    description: "Description",
  };
  return map[k] ?? k;
}

const rowsSchema = z.array(z.array(z.string().max(2000)).max(100)).max(5000, "Import at most 5,000 rows at a time.");

export async function runImport(entity: ImportEntity, rows: string[][], mapping: ImportMapping, options: ImportOptions) {
  return act(async (user): Promise<ImportSummary> => {
    assert(canManageSettings(user), "Only admins can import data.");
    const data = rowsSchema.parse(rows);
    const targets = Object.values(mapping);
    const summary: ImportSummary = { created: 0, updated: 0, skipped: 0, errors: [] };
    const defs = await getFieldDefs(user.orgId, entity);
    const users = await prisma.user.findMany({ where: { orgId: user.orgId, isActive: true }, select: { id: true, email: true } });
    const ownerByEmail = new Map(users.map((u) => [u.email.toLowerCase(), u.id]));

    if (entity === "CONTACT" && !targets.includes("firstName")) fail("Map a column to First name.");
    if (entity === "COMPANY" && !targets.includes("name")) fail("Map a column to Company name.");

    const get = (row: string[], key: string) => {
      const idx = Object.entries(mapping).find(([, v]) => v === key)?.[0];
      return idx === undefined ? "" : (row[Number(idx)] ?? "").trim();
    };
    const customFrom = (row: string[]): CustomValues => {
      const out: CustomValues = {};
      for (const d of defs) {
        const v = get(row, `cf_${d.key}`);
        if (!v) continue;
        out[d.key] = d.type === "NUMBER" ? Number(v) || 0 : d.type === "CHECKBOX" ? /^(yes|true|1|y)$/i.test(v) : v;
      }
      return out;
    };

    const allTags = new Set<string>();
    for (const [i, row] of data.entries()) {
      const line = i + 2;
      try {
        const ownerEmail = get(row, "ownerEmail").toLowerCase();
        const ownerId = ownerEmail ? (ownerByEmail.get(ownerEmail) ?? user.id) : user.id;
        const tags = parseTags(get(row, "tags").replace(/;/g, ","));
        tags.forEach((t) => allTags.add(t));
        const customFields = customFrom(row);

        if (entity === "COMPANY") {
          const name = get(row, "name");
          if (!name) throw new Error("missing company name");
          const existing = await prisma.company.findFirst({ where: { orgId: user.orgId, name: { equals: name, mode: "insensitive" } } });
          const fields = {
            industry: get(row, "industry") || null,
            website: get(row, "website") || null,
            phone: get(row, "phone") || null,
            email: get(row, "email").toLowerCase() || null,
            addressLine: get(row, "addressLine") || null,
            city: get(row, "city") || null,
            state: get(row, "state") || null,
            postalCode: get(row, "postalCode") || null,
            country: get(row, "country") || "India",
            description: get(row, "description") || null,
          };
          if (existing && options.onDuplicate === "skip") {
            summary.skipped++;
            continue;
          }
          if (existing && options.onDuplicate === "update") {
            await prisma.company.update({
              where: { id: existing.id },
              data: { ...stripNulls(fields), tags: Array.from(new Set([...existing.tags, ...tags])), customFields: { ...(existing.customFields as object), ...customFields } },
            });
            summary.updated++;
            continue;
          }
          await prisma.company.create({ data: { orgId: user.orgId, name, ...fields, tags, customFields, ownerId } });
          summary.created++;
        } else {
          const firstName = get(row, "firstName");
          if (!firstName) throw new Error("missing first name");
          const email = get(row, "email").toLowerCase() || null;
          const phone = get(row, "phone") || null;
          const whatsappNumber = get(row, "whatsappNumber") || null;
          const phoneNormalized = normalizePhone(phone) ?? normalizePhone(whatsappNumber);
          let companyId: string | null = null;
          const companyName = get(row, "companyName");
          if (companyName) {
            const c = await prisma.company.findFirst({ where: { orgId: user.orgId, name: { equals: companyName, mode: "insensitive" } }, select: { id: true } });
            companyId = c ? c.id : (await prisma.company.create({ data: { orgId: user.orgId, name: companyName, ownerId }, select: { id: true } })).id;
          }
          const existing = await prisma.contact.findFirst({
            where: { orgId: user.orgId, OR: [...(email ? [{ email }] : []), ...(phoneNormalized ? [{ phoneNormalized }] : [])] },
          });
          const fields = {
            lastName: get(row, "lastName") || null,
            email,
            phone,
            phoneNormalized,
            whatsappNumber,
            jobTitle: get(row, "jobTitle") || null,
            companyId,
          };
          if (existing && options.onDuplicate === "skip") {
            summary.skipped++;
            continue;
          }
          if (existing && options.onDuplicate === "update") {
            await prisma.contact.update({
              where: { id: existing.id },
              data: { firstName, ...stripNulls(fields), tags: Array.from(new Set([...existing.tags, ...tags])), customFields: { ...(existing.customFields as object), ...customFields } },
            });
            summary.updated++;
            continue;
          }
          await prisma.contact.create({ data: { orgId: user.orgId, firstName, ...fields, tags, customFields, ownerId } });
          summary.created++;
        }
      } catch (e) {
        summary.errors.push(`Row ${line}: ${e instanceof Error ? e.message : "failed"}`);
        if (summary.errors.length > 50) {
          summary.errors.push("Stopped after 50 errors.");
          break;
        }
      }
    }
    await ensureTags(user.orgId, [...allTags]);
    await audit({ orgId: user.orgId, actorId: user.id, action: "import", entity, entityId: "*", entityLabel: `${summary.created} created, ${summary.updated} updated, ${summary.skipped} skipped`, after: { errors: summary.errors.length } });
    revalidatePath(entity === "CONTACT" ? "/contacts" : "/companies");
    return summary;
  });
}

function stripNulls<T extends Record<string, unknown>>(o: T): Partial<T> {
  return Object.fromEntries(Object.entries(o).filter(([, v]) => v !== null && v !== "")) as Partial<T>;
}
