import { prisma } from "./db";

export async function ensureTags(orgId: string, names: string[]) {
  if (names.length === 0) return;
  await prisma.tag.createMany({
    data: names.map((name) => ({ orgId, name })),
    skipDuplicates: true,
  });
}

export function parseTags(raw: string) {
  return Array.from(
    new Set(
      raw
        .split(",")
        .map((t) => t.trim())
        .filter(Boolean)
        .map((t) => t.slice(0, 40)),
    ),
  );
}
