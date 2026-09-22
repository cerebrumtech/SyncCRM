"use server";

import { revalidatePath } from "next/cache";
import { z } from "zod";
import { prisma } from "@/lib/db";
import { randomToken } from "@/lib/auth";
import { audit } from "@/lib/audit";
import { assert, canAssignRole, canManageUsers, isOwner } from "@/lib/permissions";
import { act, fail, str } from "./_helpers";
import type { Role } from "@/generated/prisma/client";

const roleSchema = z.enum(["OWNER", "ADMIN", "MEMBER"]);

export async function inviteUser(formData: FormData) {
  return act(async (user) => {
    assert(canManageUsers(user));
    const email = z.string().trim().toLowerCase().email("Enter a valid email").parse(str(formData, "email"));
    const role = roleSchema.parse(str(formData, "role") || "MEMBER");
    assert(canAssignRole(user, role), "Only an owner can invite another owner.");

    const existing = await prisma.user.findUnique({ where: { email } });
    if (existing) fail("A user with this email already exists.");

    await prisma.invite.updateMany({
      where: { orgId: user.orgId, email, status: "PENDING" },
      data: { status: "REVOKED" },
    });
    const invite = await prisma.invite.create({
      data: {
        orgId: user.orgId,
        email,
        role,
        token: randomToken(24),
        invitedById: user.id,
        expiresAt: new Date(Date.now() + 7 * 86_400_000),
      },
    });
    await audit({
      orgId: user.orgId,
      actorId: user.id,
      action: "invite",
      entity: "User",
      entityId: invite.id,
      entityLabel: email,
      after: { email, role },
    });
    revalidatePath("/settings/users");
    return { link: `${process.env.APP_URL ?? ""}/invite/${invite.token}` };
  });
}

export async function revokeInvite(inviteId: string) {
  return act(async (user) => {
    assert(canManageUsers(user));
    const invite = await prisma.invite.findFirst({ where: { id: inviteId, orgId: user.orgId } });
    if (!invite) fail("Invite not found.");
    await prisma.invite.update({ where: { id: invite.id }, data: { status: "REVOKED" } });
    await audit({
      orgId: user.orgId,
      actorId: user.id,
      action: "revoke_invite",
      entity: "User",
      entityId: invite.id,
      entityLabel: invite.email,
    });
    revalidatePath("/settings/users");
  });
}

export async function updateUserRole(userId: string, roleInput: string) {
  return act(async (actor) => {
    assert(canManageUsers(actor));
    const role = roleSchema.parse(roleInput);
    const target = await prisma.user.findFirst({ where: { id: userId, orgId: actor.orgId } });
    if (!target) fail("User not found.");
    assert(canAssignRole(actor, role), "Only an owner can grant the owner role.");
    assert(target.role !== "OWNER" || isOwner(actor), "Only an owner can change another owner's role.");
    if (target.id === actor.id && role !== "OWNER" && actor.role === "OWNER") {
      await ensureAnotherOwner(actor.orgId, actor.id);
    }
    await prisma.user.update({ where: { id: target.id }, data: { role } });
    await audit({
      orgId: actor.orgId,
      actorId: actor.id,
      action: "update_role",
      entity: "User",
      entityId: target.id,
      entityLabel: target.email,
      before: { role: target.role },
      after: { role },
    });
    revalidatePath("/settings/users");
  });
}

async function ensureAnotherOwner(orgId: string, exceptUserId: string) {
  const others = await prisma.user.count({
    where: { orgId, role: "OWNER", isActive: true, id: { not: exceptUserId } },
  });
  if (others === 0) fail("The workspace must keep at least one active owner.");
}

export async function deactivateUser(userId: string, reassignToId: string | null) {
  return act(async (actor) => {
    assert(canManageUsers(actor));
    if (userId === actor.id) fail("You can't deactivate your own account.");
    const target = await prisma.user.findFirst({ where: { id: userId, orgId: actor.orgId } });
    if (!target) fail("User not found.");
    assert(target.role !== "OWNER" || isOwner(actor), "Only an owner can deactivate another owner.");
    if (target.role === "OWNER") await ensureAnotherOwner(actor.orgId, target.id);

    let reassignTo: { id: string } | null = null;
    if (reassignToId) {
      reassignTo = await prisma.user.findFirst({
        where: { id: reassignToId, orgId: actor.orgId, isActive: true },
        select: { id: true },
      });
      if (!reassignTo) fail("Choose an active user to reassign records to.");
    }

    await prisma.$transaction(async (tx) => {
      const where = { orgId: actor.orgId, ownerId: target.id };
      const newOwner = reassignTo?.id ?? null;
      await tx.contact.updateMany({ where, data: { ownerId: newOwner } });
      await tx.company.updateMany({ where, data: { ownerId: newOwner } });
      await tx.deal.updateMany({ where: { ...where, status: "OPEN" }, data: { ownerId: newOwner } });
      await tx.activity.updateMany({
        where: { orgId: actor.orgId, assigneeId: target.id, status: "OPEN" },
        data: { assigneeId: newOwner },
      });
      await tx.session.deleteMany({ where: { userId: target.id } });
      await tx.user.update({ where: { id: target.id }, data: { isActive: false } });
    });
    await audit({
      orgId: actor.orgId,
      actorId: actor.id,
      action: "deactivate",
      entity: "User",
      entityId: target.id,
      entityLabel: target.email,
      after: { reassignedTo: reassignTo?.id ?? null },
    });
    revalidatePath("/settings/users");
  });
}

export async function reactivateUser(userId: string) {
  return act(async (actor) => {
    assert(canManageUsers(actor));
    const target = await prisma.user.findFirst({ where: { id: userId, orgId: actor.orgId } });
    if (!target) fail("User not found.");
    await prisma.user.update({ where: { id: target.id }, data: { isActive: true } });
    await audit({
      orgId: actor.orgId,
      actorId: actor.id,
      action: "reactivate",
      entity: "User",
      entityId: target.id,
      entityLabel: target.email,
    });
    revalidatePath("/settings/users");
  });
}

export async function createTeam(formData: FormData) {
  return act(async (actor) => {
    assert(canManageUsers(actor));
    const name = z.string().trim().min(2, "Team name is too short").parse(str(formData, "name"));
    const exists = await prisma.team.findUnique({ where: { orgId_name: { orgId: actor.orgId, name } } });
    if (exists) fail("A team with that name already exists.");
    const team = await prisma.team.create({ data: { orgId: actor.orgId, name } });
    await audit({ orgId: actor.orgId, actorId: actor.id, action: "create", entity: "Team", entityId: team.id, entityLabel: name });
    revalidatePath("/settings/teams");
  });
}

export async function deleteTeam(teamId: string) {
  return act(async (actor) => {
    assert(canManageUsers(actor));
    const team = await prisma.team.findFirst({ where: { id: teamId, orgId: actor.orgId } });
    if (!team) fail("Team not found.");
    await prisma.team.delete({ where: { id: team.id } });
    await audit({ orgId: actor.orgId, actorId: actor.id, action: "delete", entity: "Team", entityId: team.id, entityLabel: team.name });
    revalidatePath("/settings/teams");
  });
}

export async function setTeamMembers(teamId: string, userIds: string[]) {
  return act(async (actor) => {
    assert(canManageUsers(actor));
    const team = await prisma.team.findFirst({ where: { id: teamId, orgId: actor.orgId } });
    if (!team) fail("Team not found.");
    const users = await prisma.user.findMany({
      where: { orgId: actor.orgId, id: { in: userIds } },
      select: { id: true },
    });
    await prisma.$transaction([
      prisma.teamMember.deleteMany({ where: { teamId: team.id } }),
      prisma.teamMember.createMany({ data: users.map((u) => ({ teamId: team.id, userId: u.id })) }),
    ]);
    await audit({
      orgId: actor.orgId,
      actorId: actor.id,
      action: "update_members",
      entity: "Team",
      entityId: team.id,
      entityLabel: team.name,
      after: { userIds: users.map((u) => u.id) },
    });
    revalidatePath("/settings/teams");
  });
}

export async function updateOrganization(formData: FormData) {
  return act(async (actor) => {
    assert(isOwner(actor) || actor.role === "ADMIN");
    const name = z.string().trim().min(2, "Name is too short").parse(str(formData, "name"));
    const before = await prisma.organization.findUnique({ where: { id: actor.orgId } });
    await prisma.organization.update({ where: { id: actor.orgId }, data: { name } });
    await audit({
      orgId: actor.orgId,
      actorId: actor.id,
      action: "update",
      entity: "Organization",
      entityId: actor.orgId,
      entityLabel: name,
      before: { name: before?.name },
      after: { name },
    });
    revalidatePath("/", "layout");
  });
}

const modeSchema = z.enum(["off", "warn", "block"]);

export async function updateDedupeRules(formData: FormData) {
  return act(async (actor) => {
    assert(canManageUsers(actor));
    const rules = {
      contactEmail: modeSchema.parse(str(formData, "contactEmail")),
      contactPhone: modeSchema.parse(str(formData, "contactPhone")),
      companyName: modeSchema.parse(str(formData, "companyName")),
    };
    const org = await prisma.organization.findUnique({ where: { id: actor.orgId } });
    const settings = { ...((org?.settings ?? {}) as object), dedupe: rules };
    await prisma.organization.update({ where: { id: actor.orgId }, data: { settings } });
    await audit({ orgId: actor.orgId, actorId: actor.id, action: "update_dedupe_rules", entity: "Organization", entityId: actor.orgId, entityLabel: org?.name, after: rules });
    revalidatePath("/settings/organization");
  });
}

export type { Role };
