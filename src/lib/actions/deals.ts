"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { z } from "zod";
import { prisma } from "@/lib/db";
import { audit } from "@/lib/audit";
import { assert, canDeleteRecord, canEditRecord } from "@/lib/permissions";
import { fromDateInput, toNumber } from "@/lib/format";
import { getFieldDefs, parseCustomFields } from "@/lib/custom-fields";
import { ensureTags, parseTags } from "@/lib/tags";
import { act, fail, optStr, str } from "./_helpers";
import type { Deal, Stage } from "@/generated/prisma/client";

const dealSchema = z.object({
  title: z.string().trim().min(1, "Deal title is required").max(160),
  pipelineId: z.string().min(1, "Choose a pipeline"),
  stageId: z.string().min(1, "Choose a stage"),
  amount: z.coerce.number().min(0, "Amount can't be negative").max(1e12),
  expectedCloseDate: z.string().optional().nullable(),
  contactId: z.string().optional().nullable(),
  companyId: z.string().optional().nullable(),
  ownerId: z.string().optional().nullable(),
});

function revalidateDeal(id?: string) {
  revalidatePath("/deals");
  revalidatePath("/dashboard");
  if (id) revalidatePath(`/deals/${id}`);
}

async function readDealForm(orgId: string, formData: FormData) {
  const parsed = dealSchema.parse({
    title: str(formData, "title"),
    pipelineId: str(formData, "pipelineId"),
    stageId: str(formData, "stageId"),
    amount: str(formData, "amount") || "0",
    expectedCloseDate: optStr(formData, "expectedCloseDate"),
    contactId: optStr(formData, "contactId"),
    companyId: optStr(formData, "companyId"),
    ownerId: optStr(formData, "ownerId"),
  });
  const stage = await prisma.stage.findFirst({ where: { id: parsed.stageId, pipelineId: parsed.pipelineId, pipeline: { orgId } } });
  if (!stage) fail("Stage doesn't belong to the chosen pipeline.");
  if (parsed.contactId) {
    const c = await prisma.contact.findFirst({ where: { id: parsed.contactId, orgId }, select: { id: true, companyId: true } });
    if (!c) fail("Contact not found.");
    if (!parsed.companyId && c.companyId) parsed.companyId = c.companyId;
  }
  if (parsed.companyId) {
    const c = await prisma.company.findFirst({ where: { id: parsed.companyId, orgId }, select: { id: true } });
    if (!c) fail("Company not found.");
  }
  if (parsed.ownerId) {
    const u = await prisma.user.findFirst({ where: { id: parsed.ownerId, orgId, isActive: true }, select: { id: true } });
    if (!u) fail("Owner must be an active user.");
  }
  const defs = await getFieldDefs(orgId, "DEAL");
  const customFields = parseCustomFields(defs, formData);
  const tags = parseTags(str(formData, "tags"));
  return {
    data: {
      ...parsed,
      contactId: parsed.contactId ?? null,
      companyId: parsed.companyId ?? null,
      ownerId: parsed.ownerId ?? null,
      expectedCloseDate: fromDateInput(parsed.expectedCloseDate),
      customFields,
      tags,
    },
    stage,
  };
}

// Returns human labels of the stage's required fields the deal has not filled in.
function missingStageFields(stage: Stage, deal: { amount: unknown; expectedCloseDate: Date | null; contactId: string | null; companyId: string | null; ownerId: string | null; customFields: unknown }) {
  const cf = (deal.customFields ?? {}) as Record<string, unknown>;
  const labels: Record<string, string> = { amount: "Amount", expectedCloseDate: "Expected close date", contactId: "Contact", companyId: "Company", ownerId: "Owner" };
  return stage.requiredFields.filter((f) => {
    if (f === "amount") return toNumber(deal.amount) <= 0;
    if (f in labels) return !deal[f as keyof typeof labels & keyof typeof deal];
    const v = cf[f.replace(/^cf_/, "")];
    return v === null || v === undefined || v === "" || v === false;
  }).map((f) => labels[f] ?? f.replace(/^cf_/, ""));
}

function statusFor(stage: Stage) {
  return stage.isWon ? ("WON" as const) : stage.isLost ? ("LOST" as const) : ("OPEN" as const);
}

export async function createDeal(formData: FormData) {
  return act(async (user) => {
    const { data, stage } = await readDealForm(user.orgId, formData);
    const missing = missingStageFields(stage, { ...data, amount: data.amount });
    if (missing.length) fail(`To be in "${stage.name}" a deal needs: ${missing.join(", ")}.`);
    const last = await prisma.deal.findFirst({ where: { stageId: stage.id }, orderBy: { position: "desc" }, select: { position: true } });
    const status = statusFor(stage);
    const deal = await prisma.deal.create({
      data: {
        ...data,
        orgId: user.orgId,
        ownerId: data.ownerId ?? user.id,
        status,
        closedAt: status === "OPEN" ? null : new Date(),
        position: (last?.position ?? -1) + 1,
      },
    });
    await ensureTags(user.orgId, data.tags);
    await audit({ orgId: user.orgId, actorId: user.id, action: "create", entity: "DEAL", entityId: deal.id, entityLabel: deal.title, after: { ...data, stage: stage.name } });
    revalidateDeal();
    return { id: deal.id };
  });
}

export async function updateDeal(id: string, formData: FormData) {
  return act(async (user) => {
    const existing = await prisma.deal.findFirst({ where: { id, orgId: user.orgId }, include: { stage: true } });
    if (!existing) fail("Deal not found.");
    assert(await canEditRecord(user, "DEAL", existing));
    const { data, stage } = await readDealForm(user.orgId, formData);
    if (stage.id !== existing.stageId) {
      const missing = missingStageFields(stage, data);
      if (missing.length) fail(`To move to "${stage.name}" this deal needs: ${missing.join(", ")}.`);
    }
    const status = statusFor(stage);
    const amountIsManual = existing.amountIsManual;
    const deal = await prisma.deal.update({
      where: { id },
      data: {
        ...data,
        amount: amountIsManual ? data.amount : existing.amount,
        status,
        closedAt: status === "OPEN" ? null : (existing.closedAt ?? new Date()),
        lostReason: status === "LOST" ? existing.lostReason : null,
      },
    });
    await ensureTags(user.orgId, data.tags);
    await audit({ orgId: user.orgId, actorId: user.id, action: "update", entity: "DEAL", entityId: id, entityLabel: deal.title, before: { ...existing, stage: existing.stage.name }, after: { ...data, stage: stage.name } });
    revalidateDeal(id);
  });
}

export async function deleteDeal(id: string) {
  return act(async (user) => {
    const existing = await prisma.deal.findFirst({ where: { id, orgId: user.orgId } });
    if (!existing) fail("Deal not found.");
    assert(canDeleteRecord(user, existing), "Only the owner or an admin can delete this deal.");
    await prisma.deal.delete({ where: { id } });
    await audit({ orgId: user.orgId, actorId: user.id, action: "delete", entity: "DEAL", entityId: id, entityLabel: existing.title, before: existing });
    revalidateDeal();
    redirect("/deals");
  });
}

export type MoveResult = { needsLostReason?: boolean; missing?: string[] };

// Kanban drop and stage stepper. Position is the index within the target column.
export async function moveDeal(dealId: string, stageId: string, position: number | null, lostReason?: string | null): Promise<{ ok: true; data?: MoveResult } | { ok: false; error: string }> {
  return act(async (user) => {
    const deal = await prisma.deal.findFirst({ where: { id: dealId, orgId: user.orgId }, include: { stage: true } });
    if (!deal) fail("Deal not found.");
    assert(await canEditRecord(user, "DEAL", deal), "You can't move a deal you don't own.");
    const stage = await prisma.stage.findFirst({ where: { id: stageId, pipelineId: deal.pipelineId } });
    if (!stage) fail("Stage not found in this pipeline.");

    if (stage.id !== deal.stageId) {
      const missing = missingStageFields(stage, deal);
      if (missing.length) return { missing } satisfies MoveResult;
      if (stage.isLost && !lostReason && !deal.lostReason) return { needsLostReason: true } satisfies MoveResult;
    }

    const status = statusFor(stage);
    await prisma.$transaction(async (tx) => {
      const siblings = await tx.deal.findMany({ where: { stageId: stage.id, id: { not: deal.id } }, orderBy: { position: "asc" }, select: { id: true } });
      const idx = position === null || position > siblings.length ? siblings.length : Math.max(0, position);
      const ordered = [...siblings.slice(0, idx), { id: deal.id }, ...siblings.slice(idx)];
      for (const [i, s] of ordered.entries()) {
        await tx.deal.update({
          where: { id: s.id },
          data:
            s.id === deal.id
              ? {
                  position: i,
                  stageId: stage.id,
                  status,
                  closedAt: status === "OPEN" ? null : (deal.closedAt ?? new Date()),
                  lostReason: status === "LOST" ? (lostReason ?? deal.lostReason) : null,
                }
              : { position: i },
        });
      }
    });
    if (stage.id !== deal.stageId) {
      await audit({
        orgId: user.orgId,
        actorId: user.id,
        action: status === "WON" ? "won" : status === "LOST" ? "lost" : deal.status !== "OPEN" ? "reopen" : "stage_change",
        entity: "DEAL",
        entityId: deal.id,
        entityLabel: deal.title,
        before: { stage: deal.stage.name },
        after: { stage: stage.name, ...(lostReason ? { lostReason } : {}) },
      });
    }
    revalidateDeal(deal.id);
    return {} satisfies MoveResult;
  });
}

export async function setLostReason(dealId: string, reason: string) {
  return act(async (user) => {
    const deal = await prisma.deal.findFirst({ where: { id: dealId, orgId: user.orgId } });
    if (!deal) fail("Deal not found.");
    assert(await canEditRecord(user, "DEAL", deal));
    await prisma.deal.update({ where: { id: deal.id }, data: { lostReason: reason.trim().slice(0, 200) || null } });
    revalidateDeal(deal.id);
  });
}

// Creates a linked copy of the deal in another pipeline (e.g. Sales -> Onboarding).
export async function handoffDeal(dealId: string, targetPipelineId: string) {
  return act(async (user) => {
    const deal = await prisma.deal.findFirst({ where: { id: dealId, orgId: user.orgId }, include: { lineItems: true } });
    if (!deal) fail("Deal not found.");
    assert(await canEditRecord(user, "DEAL", deal));
    if (targetPipelineId === deal.pipelineId) fail("Choose a different pipeline.");
    const target = await prisma.pipeline.findFirst({ where: { id: targetPipelineId, orgId: user.orgId }, include: { stages: { orderBy: { position: "asc" } } } });
    if (!target) fail("Pipeline not found.");
    const firstStage = target.stages.find((s) => !s.isWon && !s.isLost) ?? target.stages[0];
    if (!firstStage) fail("Target pipeline has no stages.");
    const already = await prisma.deal.findFirst({ where: { sourceDealId: deal.id, pipelineId: target.id }, select: { id: true } });
    if (already) return { id: already.id, existed: true };
    const last = await prisma.deal.findFirst({ where: { stageId: firstStage.id }, orderBy: { position: "desc" }, select: { position: true } });
    const copy = await prisma.deal.create({
      data: {
        orgId: user.orgId,
        title: deal.title,
        pipelineId: target.id,
        stageId: firstStage.id,
        status: "OPEN",
        amount: deal.amount,
        amountIsManual: deal.amountIsManual,
        contactId: deal.contactId,
        companyId: deal.companyId,
        ownerId: deal.ownerId,
        tags: deal.tags,
        customFields: deal.customFields as object,
        sourceDealId: deal.id,
        position: (last?.position ?? -1) + 1,
        lineItems: {
          create: deal.lineItems.map((li) => ({
            productId: li.productId,
            name: li.name,
            quantity: li.quantity,
            unitPrice: li.unitPrice,
            discountPercent: li.discountPercent,
            taxRate: li.taxRate,
            total: li.total,
            position: li.position,
          })),
        },
      },
    });
    await audit({ orgId: user.orgId, actorId: user.id, action: "handoff", entity: "DEAL", entityId: deal.id, entityLabel: deal.title, after: { toPipeline: target.name, newDealId: copy.id } });
    revalidateDeal(deal.id);
    return { id: copy.id, existed: false };
  });
}

export async function searchDeals(q: string) {
  return act(async (user) => {
    const rows = await prisma.deal.findMany({
      where: { orgId: user.orgId, title: { contains: q, mode: "insensitive" } },
      select: { id: true, title: true, pipeline: { select: { name: true } } },
      take: 8,
      orderBy: { updatedAt: "desc" },
    });
    return rows.map((r) => ({ id: r.id, label: r.title, sub: r.pipeline.name }));
  });
}

export type { Deal };
