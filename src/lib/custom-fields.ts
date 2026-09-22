import { prisma } from "./db";
import type { CustomFieldDefinition, EntityType } from "@/generated/prisma/client";
import { ActionError } from "./actions/_helpers";

export type CustomValues = Record<string, string | number | boolean | null>;

export function getFieldDefs(orgId: string, entity: EntityType) {
  return prisma.customFieldDefinition.findMany({
    where: { orgId, entity },
    orderBy: { position: "asc" },
  });
}

// Reads cf_<key> inputs from a form and validates them against the org's definitions.
export function parseCustomFields(defs: CustomFieldDefinition[], formData: FormData): CustomValues {
  const out: CustomValues = {};
  for (const def of defs) {
    const raw = formData.get(`cf_${def.key}`);
    const value = typeof raw === "string" ? raw.trim() : "";
    if (def.type === "CHECKBOX") {
      out[def.key] = raw === "on" || raw === "true";
      continue;
    }
    if (!value) {
      if (def.required) throw new ActionError(`${def.label} is required.`);
      out[def.key] = null;
      continue;
    }
    switch (def.type) {
      case "NUMBER": {
        const n = Number(value);
        if (!Number.isFinite(n)) throw new ActionError(`${def.label} must be a number.`);
        out[def.key] = n;
        break;
      }
      case "SELECT":
        if (!def.options.includes(value)) throw new ActionError(`${def.label} has an invalid option.`);
        out[def.key] = value;
        break;
      default:
        out[def.key] = value;
    }
  }
  return out;
}

export function customValues(json: unknown): CustomValues {
  return json && typeof json === "object" && !Array.isArray(json) ? (json as CustomValues) : {};
}
