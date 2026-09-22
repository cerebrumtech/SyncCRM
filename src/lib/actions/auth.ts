"use server";

import { redirect } from "next/navigation";
import { z } from "zod";
import { prisma } from "@/lib/db";
import { createSession, destroySession, hashPassword, verifyPassword } from "@/lib/auth";
import { DEFAULT_PIPELINES } from "@/lib/defaults";
import { audit } from "@/lib/audit";

export type FormState =
  | { error?: string; success?: string; values?: Record<string, string> }
  | undefined;

const loginSchema = z.object({
  email: z.string().trim().toLowerCase().email("Enter a valid email"),
  password: z.string().min(1, "Enter your password"),
  next: z.string().optional(),
});

export async function login(_prev: FormState, formData: FormData): Promise<FormState> {
  const parsed = loginSchema.safeParse(Object.fromEntries(formData));
  const values = { email: String(formData.get("email") ?? "") };
  if (!parsed.success) {
    return { error: parsed.error.issues[0]?.message ?? "Invalid input", values };
  }
  const { email, password, next } = parsed.data;
  const user = await prisma.user.findUnique({ where: { email } });
  if (!user || !(await verifyPassword(password, user.passwordHash))) {
    return { error: "Incorrect email or password.", values };
  }
  if (!user.isActive) {
    return { error: "This account has been deactivated. Contact your administrator.", values };
  }
  await prisma.user.update({ where: { id: user.id }, data: { lastLoginAt: new Date() } });
  await createSession(user.id);
  redirect(next && next.startsWith("/") ? next : "/dashboard");
}

export async function logout() {
  await destroySession();
  redirect("/login");
}

const setupSchema = z.object({
  orgName: z.string().trim().min(2, "Organisation name is too short"),
  name: z.string().trim().min(2, "Enter your name"),
  email: z.string().trim().toLowerCase().email("Enter a valid email"),
  password: z.string().min(8, "Password must be at least 8 characters"),
});

export async function setupOrganization(_prev: FormState, formData: FormData): Promise<FormState> {
  const existing = await prisma.organization.count();
  if (existing > 0) return { error: "This workspace is already set up. Please sign in." };

  const parsed = setupSchema.safeParse(Object.fromEntries(formData));
  if (!parsed.success) {
    return { error: parsed.error.issues[0]?.message ?? "Invalid input" };
  }
  const { orgName, name, email, password } = parsed.data;

  const user = await prisma.$transaction(async (tx) => {
    const org = await tx.organization.create({ data: { name: orgName } });
    const owner = await tx.user.create({
      data: {
        orgId: org.id,
        email,
        name,
        passwordHash: await hashPassword(password),
        role: "OWNER",
        color: "#1B243E",
      },
    });
    for (const [i, p] of DEFAULT_PIPELINES.entries()) {
      await tx.pipeline.create({
        data: {
          orgId: org.id,
          name: p.name,
          position: i,
          isDefault: !!p.isDefault,
          stages: {
            create: p.stages.map((s, j) => ({
              name: s.name,
              position: j,
              probability: s.probability,
              isWon: !!s.isWon,
              isLost: !!s.isLost,
              color: s.color ?? "#0068FF",
            })),
          },
        },
      });
    }
    return owner;
  });

  await audit({
    orgId: user.orgId,
    actorId: user.id,
    action: "create",
    entity: "Organization",
    entityId: user.orgId,
    entityLabel: orgName,
  });
  await createSession(user.id);
  redirect("/dashboard");
}

const acceptSchema = z.object({
  token: z.string().min(1),
  name: z.string().trim().min(2, "Enter your name"),
  password: z.string().min(8, "Password must be at least 8 characters"),
});

export async function acceptInvite(_prev: FormState, formData: FormData): Promise<FormState> {
  const parsed = acceptSchema.safeParse(Object.fromEntries(formData));
  if (!parsed.success) {
    return { error: parsed.error.issues[0]?.message ?? "Invalid input" };
  }
  const { token, name, password } = parsed.data;
  const invite = await prisma.invite.findUnique({ where: { token } });
  if (!invite || invite.status !== "PENDING" || invite.expiresAt < new Date()) {
    return { error: "This invite link is invalid or has expired." };
  }
  const taken = await prisma.user.findUnique({ where: { email: invite.email } });
  if (taken) return { error: "An account with this email already exists. Please sign in." };

  const user = await prisma.$transaction(async (tx) => {
    const u = await tx.user.create({
      data: {
        orgId: invite.orgId,
        email: invite.email,
        name,
        passwordHash: await hashPassword(password),
        role: invite.role,
        color: pickColor(invite.email),
      },
    });
    await tx.invite.update({ where: { id: invite.id }, data: { status: "ACCEPTED" } });
    return u;
  });
  await audit({
    orgId: user.orgId,
    actorId: user.id,
    action: "accept_invite",
    entity: "User",
    entityId: user.id,
    entityLabel: user.email,
  });
  await createSession(user.id);
  redirect("/dashboard");
}

const PALETTE = ["#0068FF", "#04A2FB", "#1B243E", "#10B981", "#F59E0B", "#8B5CF6", "#EC4899", "#0EA5E9"];

export async function pickColorForEmail(email: string) {
  return pickColor(email);
}

function pickColor(seed: string) {
  let h = 0;
  for (const ch of seed) h = (h * 31 + ch.charCodeAt(0)) >>> 0;
  return PALETTE[h % PALETTE.length]!;
}
