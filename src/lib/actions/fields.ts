"use server";

import { revalidatePath } from "next/cache";
import { z } from "zod";
import { prisma } from "@/lib/db";
import { audit } from "@/lib/audit";
import { assert, canManageSettings } from "@/lib/permissions";
import { slugify } from "@/lib/format";
import { act, fail, list, str } from "./_helpers";

const entitySchema = z.enum(["CONTACT", "COMPANY", "DEAL"]);
const typeSchema = z.enum(["TEXT", "NUMBER", "DATE", "SELECT", "CHECKBOX"]);

function revalidate() {
  revalidatePath("/settings/fields");
  revalidatePath("/contacts");
  revalidatePath("/companies");
  revalidatePath("/deals");
}

export async function createField(formData: FormData) {
  return act(async (user) => {
    assert(canManageSettings(user));
    const entity = entitySchema.parse(str(formData, "entity"));
    const label = z.string().trim().min(1, "Label is required").max(60).parse(str(formData, "label"));
    const type = typeSchema.parse(str(formData, "type") || "TEXT");
    const options = type === "SELECT" ? list(formData, "options") : [];
    if (type === "SELECT" && options.length === 0) fail("Add at least one option for a dropdown field.");
    const key = slugify(label) || `field_${Date.now()}`;
    const exists = await prisma.customFieldDefinition.findUnique({ where: { orgId_entity_key: { orgId: user.orgId, entity, key } } });
    if (exists) fail("A field with this name already exists for this record type.");
    const count = await prisma.customFieldDefinition.count({ where: { orgId: user.orgId, entity } });
    const def = await prisma.customFieldDefinition.create({
      data: { orgId: user.orgId, entity, key, label, type, options, required: formData.get("required") === "on", position: count },
    });
    await audit({ orgId: user.orgId, actorId: user.id, action: "create", entity: "CustomField", entityId: def.id, entityLabel: `${entity}: ${label}`, after: { type, options } });
    revalidate();
  });
}

export async function updateField(id: string, formData: FormData) {
  return act(async (user) => {
    assert(canManageSettings(user));
    const def = await prisma.customFieldDefinition.findFirst({ where: { id, orgId: user.orgId } });
    if (!def) fail("Field not found.");
    const label = z.string().trim().min(1, "Label is required").max(60).parse(str(formData, "label"));
    const options = def.type === "SELECT" ? list(formData, "options") : [];
    if (def.type === "SELECT" && options.length === 0) fail("Add at least one option for a dropdown field.");
    await prisma.customFieldDefinition.update({ where: { id }, data: { label, options, required: formData.get("required") === "on" } });
    await audit({ orgId: user.orgId, actorId: user.id, action: "update", entity: "CustomField", entityId: id, entityLabel: `${def.entity}: ${label}`, before: { label: def.label, options: def.options, required: def.required }, after: { label, options } });
    revalidate();
  });
}

export async function deleteField(id: string) {
  return act(async (user) => {
    assert(canManageSettings(user));
    const def = await prisma.customFieldDefinition.findFirst({ where: { id, orgId: user.orgId } });
    if (!def) fail("Field not found.");
    await prisma.customFieldDefinition.delete({ where: { id } });
    await audit({ orgId: user.orgId, actorId: user.id, action: "delete", entity: "CustomField", entityId: id, entityLabel: `${def.entity}: ${def.label}` });
    revalidate();
  });
}

export async function moveField(id: string, direction: -1 | 1) {
  return act(async (user) => {
    assert(canManageSettings(user));
    const def = await prisma.customFieldDefinition.findFirst({ where: { id, orgId: user.orgId } });
    if (!def) fail("Field not found.");
    const siblings = await prisma.customFieldDefinition.findMany({ where: { orgId: user.orgId, entity: def.entity }, orderBy: { position: "asc" } });
    const idx = siblings.findIndex((s) => s.id === id);
    const j = idx + direction;
    if (j < 0 || j >= siblings.length) return;
    [siblings[idx], siblings[j]] = [siblings[j]!, siblings[idx]!];
    await prisma.$transaction(siblings.map((s, i) => prisma.customFieldDefinition.update({ where: { id: s.id }, data: { position: i } })));
    revalidate();
  });
}
