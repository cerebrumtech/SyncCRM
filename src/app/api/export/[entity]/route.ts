import { NextResponse } from "next/server";
import { getCurrentUser } from "@/lib/auth";
import { prisma } from "@/lib/db";
import { toCsv } from "@/lib/csv";
import { audit } from "@/lib/audit";
import { formatDate, fullName, toNumber } from "@/lib/format";
import { customValues, getFieldDefs } from "@/lib/custom-fields";
import type { Prisma } from "@/generated/prisma/client";

export async function GET(req: Request, ctx: RouteContext<"/api/export/[entity]">) {
  const user = await getCurrentUser();
  if (!user) return new NextResponse("Unauthorized", { status: 401 });
  const { entity } = await ctx.params;
  const sp = new URL(req.url).searchParams;
  const q = sp.get("q")?.trim() || "";
  const owner = sp.get("owner") || undefined;
  const tag = sp.get("tag") || undefined;
  const orgId = user.orgId;

  let rows: (string | number | null)[][] = [];
  let name = entity;

  if (entity === "contacts") {
    const defs = await getFieldDefs(orgId, "CONTACT");
    const where: Prisma.ContactWhereInput = {
      orgId,
      ...(owner ? { ownerId: owner } : {}),
      ...(tag ? { tags: { has: tag } } : {}),
      ...(q ? { OR: [{ firstName: { contains: q, mode: "insensitive" } }, { lastName: { contains: q, mode: "insensitive" } }, { email: { contains: q, mode: "insensitive" } }, { phone: { contains: q } }] } : {}),
    };
    const list = await prisma.contact.findMany({ where, orderBy: { createdAt: "asc" }, include: { company: { select: { name: true } }, owner: { select: { email: true } } } });
    rows = [
      ["First name", "Last name", "Email", "Phone", "WhatsApp", "Job title", "Company", "Owner email", "Tags", "Created", ...defs.map((d) => d.label)],
      ...list.map((c) => {
        const cf = customValues(c.customFields);
        return [c.firstName, c.lastName, c.email, c.phone, c.whatsappNumber, c.jobTitle, c.company?.name ?? null, c.owner?.email ?? null, c.tags.join(";"), formatDate(c.createdAt), ...defs.map((d) => (cf[d.key] == null ? null : String(cf[d.key])))];
      }),
    ];
  } else if (entity === "companies") {
    const defs = await getFieldDefs(orgId, "COMPANY");
    const where: Prisma.CompanyWhereInput = {
      orgId,
      ...(owner ? { ownerId: owner } : {}),
      ...(tag ? { tags: { has: tag } } : {}),
      ...(q ? { OR: [{ name: { contains: q, mode: "insensitive" } }, { city: { contains: q, mode: "insensitive" } }] } : {}),
    };
    const list = await prisma.company.findMany({ where, orderBy: { createdAt: "asc" }, include: { owner: { select: { email: true } } } });
    rows = [
      ["Name", "Industry", "Website", "Phone", "Email", "Address", "City", "State", "PIN", "Country", "Owner email", "Tags", "Created", ...defs.map((d) => d.label)],
      ...list.map((c) => {
        const cf = customValues(c.customFields);
        return [c.name, c.industry, c.website, c.phone, c.email, c.addressLine, c.city, c.state, c.postalCode, c.country, c.owner?.email ?? null, c.tags.join(";"), formatDate(c.createdAt), ...defs.map((d) => (cf[d.key] == null ? null : String(cf[d.key])))];
      }),
    ];
  } else if (entity === "deals") {
    const defs = await getFieldDefs(orgId, "DEAL");
    const status = sp.get("status");
    const pipeline = sp.get("pipeline");
    const where: Prisma.DealWhereInput = {
      orgId,
      ...(owner ? { ownerId: owner } : {}),
      ...(pipeline ? { pipelineId: pipeline } : {}),
      ...(status && ["OPEN", "WON", "LOST"].includes(status) ? { status: status as "OPEN" | "WON" | "LOST" } : {}),
      ...(q ? { title: { contains: q, mode: "insensitive" } } : {}),
    };
    const list = await prisma.deal.findMany({ where, orderBy: { createdAt: "asc" }, include: { pipeline: { select: { name: true } }, stage: { select: { name: true } }, contact: { select: { firstName: true, lastName: true } }, company: { select: { name: true } }, owner: { select: { email: true } } } });
    rows = [
      ["Title", "Pipeline", "Stage", "Status", "Amount", "Expected close", "Closed on", "Lost reason", "Contact", "Company", "Owner email", "Tags", "Created", ...defs.map((d) => d.label)],
      ...list.map((d) => {
        const cf = customValues(d.customFields);
        return [d.title, d.pipeline.name, d.stage.name, d.status, toNumber(d.amount), formatDate(d.expectedCloseDate), formatDate(d.closedAt), d.lostReason, d.contact ? fullName(d.contact) : null, d.company?.name ?? null, d.owner?.email ?? null, d.tags.join(";"), formatDate(d.createdAt), ...defs.map((f) => (cf[f.key] == null ? null : String(cf[f.key])))];
      }),
    ];
  } else {
    return new NextResponse("Unknown export", { status: 404 });
  }
  name = `synccrm-${entity}-${new Date().toISOString().slice(0, 10)}.csv`;
  await audit({ orgId, actorId: user.id, action: "export", entity: entity.toUpperCase(), entityId: "*", entityLabel: `${rows.length - 1} rows` });
  return new NextResponse("﻿" + toCsv(rows), {
    headers: { "Content-Type": "text/csv; charset=utf-8", "Content-Disposition": `attachment; filename="${name}"` },
  });
}
