import { prisma } from "./db";

export type DedupeMode = "off" | "warn" | "block";
export type DedupeRules = { contactEmail: DedupeMode; contactPhone: DedupeMode; companyName: DedupeMode };

export const DEFAULT_DEDUPE: DedupeRules = { contactEmail: "warn", contactPhone: "warn", companyName: "warn" };

export async function dedupeRules(orgId: string): Promise<DedupeRules> {
  const org = await prisma.organization.findUnique({ where: { id: orgId }, select: { settings: true } });
  const s = (org?.settings ?? {}) as { dedupe?: Partial<DedupeRules> };
  return { ...DEFAULT_DEDUPE, ...(s.dedupe ?? {}) };
}
