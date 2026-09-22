import { prisma } from "./db";
import type { Prisma } from "@/generated/prisma/client";

type AuditInput = {
  orgId: string;
  actorId: string | null;
  action: string;
  entity: string;
  entityId: string;
  entityLabel?: string | null;
  before?: unknown;
  after?: unknown;
};

const AUDIT_SKIP_KEYS = new Set(["passwordHash", "updatedAt", "createdAt"]);

function scrub(value: unknown): Prisma.InputJsonValue | undefined {
  if (value === undefined || value === null) return undefined;
  return JSON.parse(
    JSON.stringify(value, (key, v) => (AUDIT_SKIP_KEYS.has(key) ? undefined : v)),
  ) as Prisma.InputJsonValue;
}

export async function audit(input: AuditInput) {
  await prisma.auditLog.create({
    data: {
      orgId: input.orgId,
      actorId: input.actorId,
      action: input.action,
      entity: input.entity,
      entityId: input.entityId,
      entityLabel: input.entityLabel ?? null,
      before: scrub(input.before),
      after: scrub(input.after),
    },
  });
}
