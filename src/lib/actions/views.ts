"use server";

import { revalidatePath } from "next/cache";
import { z } from "zod";
import { prisma } from "@/lib/db";
import { isAdmin } from "@/lib/permissions";
import { act, fail } from "./_helpers";

const entitySchema = z.enum(["CONTACT", "COMPANY", "DEAL"]);
const PATH = { CONTACT: "/contacts", COMPANY: "/companies", DEAL: "/deals" } as const;

export async function saveView(entityInput: string, name: string, params: Record<string, string>, isShared: boolean) {
  return act(async (user) => {
    const entity = entitySchema.parse(entityInput);
    const clean = z.string().trim().min(1, "Give the view a name").max(60).parse(name);
    const filters = Object.fromEntries(Object.entries(params).filter(([k, v]) => v && k !== "page"));
    const view = await prisma.savedView.create({
      data: { orgId: user.orgId, entity, name: clean, filters, ownerId: user.id, isShared },
    });
    revalidatePath(PATH[entity]);
    return { id: view.id };
  });
}

export async function deleteView(id: string) {
  return act(async (user) => {
    const view = await prisma.savedView.findFirst({ where: { id, orgId: user.orgId } });
    if (!view) fail("View not found.");
    if (view.ownerId !== user.id && !isAdmin(user)) fail("You can only delete your own views.");
    await prisma.savedView.delete({ where: { id } });
    revalidatePath(PATH[view.entity]);
  });
}
