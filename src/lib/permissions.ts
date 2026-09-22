import { prisma } from "./db";
import type { EntityType, Role } from "@/generated/prisma/client";

const RANK: Record<Role, number> = { OWNER: 3, ADMIN: 2, MEMBER: 1 };

type Actor = { id: string; orgId: string; role: Role };
type OwnedRecord = { id: string; ownerId: string | null; orgId: string };

export function isAdmin(user: Actor) {
  return RANK[user.role] >= RANK.ADMIN;
}

export function isOwner(user: Actor) {
  return user.role === "OWNER";
}

export const canManageUsers = isAdmin;
export const canManageSettings = isAdmin;
export const canViewAudit = isAdmin;

export function canAssignRole(actor: Actor, target: Role) {
  if (target === "OWNER") return isOwner(actor);
  return isAdmin(actor);
}

export async function canEditRecord(user: Actor, entity: EntityType, record: OwnedRecord) {
  if (record.orgId !== user.orgId) return false;
  if (isAdmin(user)) return true;
  if (!record.ownerId || record.ownerId === user.id) return true;
  const share = await prisma.recordShare.findFirst({
    where: {
      orgId: user.orgId,
      entity,
      entityId: record.id,
      canEdit: true,
      OR: [{ userId: user.id }, { team: { members: { some: { userId: user.id } } } }],
    },
    select: { id: true },
  });
  return !!share;
}

export function canDeleteRecord(user: Actor, record: OwnedRecord) {
  if (record.orgId !== user.orgId) return false;
  return isAdmin(user) || record.ownerId === user.id;
}

export class PermissionError extends Error {
  constructor(message = "You don't have permission to do that.") {
    super(message);
    this.name = "PermissionError";
  }
}

export function assert(condition: unknown, message?: string): asserts condition {
  if (!condition) throw new PermissionError(message);
}
