"use server";

import { revalidatePath } from "next/cache";
import { z } from "zod";
import { prisma } from "@/lib/db";
import { audit } from "@/lib/audit";
import { assert, canManageSettings } from "@/lib/permissions";
import { act, fail, str } from "./_helpers";

function revalidate() {
  revalidatePath("/settings/pipelines");
  revalidatePath("/deals");
}

export async function createPipeline(formData: FormData) {
  return act(async (user) => {
    assert(canManageSettings(user));
    const name = z.string().trim().min(2, "Pipeline name is too short").max(60).parse(str(formData, "name"));
    const exists = await prisma.pipeline.findUnique({ where: { orgId_name: { orgId: user.orgId, name } } });
    if (exists) fail("A pipeline with that name already exists.");
    const count = await prisma.pipeline.count({ where: { orgId: user.orgId } });
    const pipeline = await prisma.pipeline.create({
      data: {
        orgId: user.orgId,
        name,
        position: count,
        isDefault: count === 0,
        stages: {
          create: [
            { name: "New", position: 0, probability: 10, color: "#04A2FB" },
            { name: "In Progress", position: 1, probability: 50, color: "#0068FF" },
            { name: "Won", position: 2, probability: 100, isWon: true, color: "#10B981" },
            { name: "Lost", position: 3, probability: 0, isLost: true, color: "#EF4444" },
          ],
        },
      },
    });
    await audit({ orgId: user.orgId, actorId: user.id, action: "create", entity: "Pipeline", entityId: pipeline.id, entityLabel: name });
    revalidate();
    return { id: pipeline.id };
  });
}

export async function renamePipeline(pipelineId: string, formData: FormData) {
  return act(async (user) => {
    assert(canManageSettings(user));
    const name = z.string().trim().min(2).max(60).parse(str(formData, "name"));
    const p = await prisma.pipeline.findFirst({ where: { id: pipelineId, orgId: user.orgId } });
    if (!p) fail("Pipeline not found.");
    await prisma.pipeline.update({ where: { id: p.id }, data: { name } });
    await audit({ orgId: user.orgId, actorId: user.id, action: "update", entity: "Pipeline", entityId: p.id, entityLabel: name, before: { name: p.name }, after: { name } });
    revalidate();
  });
}

export async function setDefaultPipeline(pipelineId: string) {
  return act(async (user) => {
    assert(canManageSettings(user));
    const p = await prisma.pipeline.findFirst({ where: { id: pipelineId, orgId: user.orgId } });
    if (!p) fail("Pipeline not found.");
    await prisma.$transaction([
      prisma.pipeline.updateMany({ where: { orgId: user.orgId }, data: { isDefault: false } }),
      prisma.pipeline.update({ where: { id: p.id }, data: { isDefault: true } }),
    ]);
    revalidate();
  });
}

export async function deletePipeline(pipelineId: string) {
  return act(async (user) => {
    assert(canManageSettings(user));
    const p = await prisma.pipeline.findFirst({ where: { id: pipelineId, orgId: user.orgId }, include: { _count: { select: { deals: true } } } });
    if (!p) fail("Pipeline not found.");
    if (p._count.deals > 0) fail(`This pipeline still has ${p._count.deals} deal(s). Move or delete them first.`);
    const total = await prisma.pipeline.count({ where: { orgId: user.orgId } });
    if (total <= 1) fail("You need at least one pipeline.");
    await prisma.pipeline.delete({ where: { id: p.id } });
    if (p.isDefault) {
      const first = await prisma.pipeline.findFirst({ where: { orgId: user.orgId }, orderBy: { position: "asc" } });
      if (first) await prisma.pipeline.update({ where: { id: first.id }, data: { isDefault: true } });
    }
    await audit({ orgId: user.orgId, actorId: user.id, action: "delete", entity: "Pipeline", entityId: p.id, entityLabel: p.name });
    revalidate();
  });
}

const stageSchema = z.object({
  name: z.string().trim().min(1, "Stage name is required").max(40),
  probability: z.coerce.number().int().min(0).max(100),
  color: z.string().regex(/^#[0-9a-fA-F]{6}$/, "Invalid colour"),
  kind: z.enum(["OPEN", "WON", "LOST"]),
  requiredFields: z.array(z.string()),
});

export type StageInput = z.infer<typeof stageSchema>;

export async function createStage(pipelineId: string, input: StageInput) {
  return act(async (user) => {
    assert(canManageSettings(user));
    const data = stageSchema.parse(input);
    const p = await prisma.pipeline.findFirst({ where: { id: pipelineId, orgId: user.orgId }, include: { stages: true } });
    if (!p) fail("Pipeline not found.");
    // closed stages sit at the end; new open stages go before them
    const openStages = p.stages.filter((s) => !s.isWon && !s.isLost);
    const position = data.kind === "OPEN" ? openStages.length : p.stages.length;
    await prisma.$transaction(async (tx) => {
      if (data.kind === "OPEN") {
        await tx.stage.updateMany({ where: { pipelineId: p.id, position: { gte: position } }, data: { position: { increment: 1 } } });
      }
      await tx.stage.create({
        data: {
          pipelineId: p.id,
          name: data.name,
          position,
          probability: data.kind === "WON" ? 100 : data.kind === "LOST" ? 0 : data.probability,
          color: data.color,
          isWon: data.kind === "WON",
          isLost: data.kind === "LOST",
          requiredFields: data.requiredFields,
        },
      });
    });
    await audit({ orgId: user.orgId, actorId: user.id, action: "add_stage", entity: "Pipeline", entityId: p.id, entityLabel: p.name, after: data });
    revalidate();
  });
}

export async function updateStage(stageId: string, input: StageInput) {
  return act(async (user) => {
    assert(canManageSettings(user));
    const data = stageSchema.parse(input);
    const s = await prisma.stage.findFirst({ where: { id: stageId, pipeline: { orgId: user.orgId } }, include: { pipeline: true } });
    if (!s) fail("Stage not found.");
    await prisma.stage.update({
      where: { id: s.id },
      data: {
        name: data.name,
        probability: data.kind === "WON" ? 100 : data.kind === "LOST" ? 0 : data.probability,
        color: data.color,
        isWon: data.kind === "WON",
        isLost: data.kind === "LOST",
        requiredFields: data.requiredFields,
      },
    });
    await audit({ orgId: user.orgId, actorId: user.id, action: "update_stage", entity: "Pipeline", entityId: s.pipelineId, entityLabel: s.pipeline.name, before: { name: s.name, probability: s.probability }, after: data });
    revalidate();
  });
}

export async function reorderStages(pipelineId: string, orderedIds: string[]) {
  return act(async (user) => {
    assert(canManageSettings(user));
    const p = await prisma.pipeline.findFirst({ where: { id: pipelineId, orgId: user.orgId }, include: { stages: true } });
    if (!p) fail("Pipeline not found.");
    const known = new Set(p.stages.map((s) => s.id));
    if (orderedIds.length !== known.size || orderedIds.some((id) => !known.has(id))) fail("Stage list is out of date. Refresh and try again.");
    await prisma.$transaction(orderedIds.map((id, i) => prisma.stage.update({ where: { id }, data: { position: i } })));
    revalidate();
  });
}

export async function deleteStage(stageId: string, moveToStageId: string | null) {
  return act(async (user) => {
    assert(canManageSettings(user));
    const s = await prisma.stage.findFirst({ where: { id: stageId, pipeline: { orgId: user.orgId } }, include: { pipeline: { include: { stages: true } }, _count: { select: { deals: true } } } });
    if (!s) fail("Stage not found.");
    if (s.pipeline.stages.length <= 2) fail("A pipeline needs at least two stages.");
    if (s._count.deals > 0) {
      const target = s.pipeline.stages.find((x) => x.id === moveToStageId && x.id !== s.id);
      if (!target) fail(`${s._count.deals} deal(s) are in this stage. Choose a stage to move them to.`);
      await prisma.deal.updateMany({ where: { stageId: s.id }, data: { stageId: target.id, status: target.isWon ? "WON" : target.isLost ? "LOST" : "OPEN" } });
    }
    await prisma.stage.delete({ where: { id: s.id } });
    const remaining = await prisma.stage.findMany({ where: { pipelineId: s.pipelineId }, orderBy: { position: "asc" } });
    await prisma.$transaction(remaining.map((st, i) => prisma.stage.update({ where: { id: st.id }, data: { position: i } })));
    await audit({ orgId: user.orgId, actorId: user.id, action: "delete_stage", entity: "Pipeline", entityId: s.pipelineId, entityLabel: s.pipeline.name, before: { stage: s.name } });
    revalidate();
  });
}
