"use server";

import { revalidatePath } from "next/cache";
import { z } from "zod";
import { prisma } from "@/lib/db";
import { audit } from "@/lib/audit";
import { assert, canEditRecord, canManageSettings } from "@/lib/permissions";
import { toNumber } from "@/lib/format";
import { act, fail, optStr, str } from "./_helpers";

const productSchema = z.object({
  name: z.string().trim().min(1, "Product name is required").max(160),
  sku: z.string().trim().max(60).optional().nullable(),
  description: z.string().trim().max(2000).optional().nullable(),
  price: z.coerce.number().min(0, "Price can't be negative").max(1e12),
  taxRate: z.coerce.number().min(0).max(100),
  imageUrl: z.string().trim().max(500).optional().nullable(),
  isActive: z.boolean(),
});

function readProductForm(formData: FormData) {
  const parsed = productSchema.parse({
    name: str(formData, "name"),
    sku: optStr(formData, "sku"),
    description: optStr(formData, "description"),
    price: str(formData, "price") || "0",
    taxRate: str(formData, "taxRate") || "0",
    imageUrl: optStr(formData, "imageUrl"),
    isActive: formData.get("isActive") !== "off",
  });
  return { ...parsed, sku: parsed.sku || null };
}

export async function createProduct(formData: FormData) {
  return act(async (user) => {
    assert(canManageSettings(user), "Only admins can manage the product catalogue.");
    const data = readProductForm(formData);
    if (data.sku) {
      const dup = await prisma.product.findUnique({ where: { orgId_sku: { orgId: user.orgId, sku: data.sku } } });
      if (dup) fail(`SKU ${data.sku} is already used by "${dup.name}".`);
    }
    const product = await prisma.product.create({ data: { ...data, orgId: user.orgId } });
    await audit({ orgId: user.orgId, actorId: user.id, action: "create", entity: "Product", entityId: product.id, entityLabel: product.name, after: data });
    revalidatePath("/products");
  });
}

export async function updateProduct(id: string, formData: FormData) {
  return act(async (user) => {
    assert(canManageSettings(user), "Only admins can manage the product catalogue.");
    const existing = await prisma.product.findFirst({ where: { id, orgId: user.orgId } });
    if (!existing) fail("Product not found.");
    const data = readProductForm(formData);
    if (data.sku) {
      const dup = await prisma.product.findFirst({ where: { orgId: user.orgId, sku: data.sku, id: { not: id } } });
      if (dup) fail(`SKU ${data.sku} is already used by "${dup.name}".`);
    }
    await prisma.product.update({ where: { id }, data });
    await audit({ orgId: user.orgId, actorId: user.id, action: "update", entity: "Product", entityId: id, entityLabel: data.name, before: existing, after: data });
    revalidatePath("/products");
  });
}

export async function setProductActive(id: string, isActive: boolean) {
  return act(async (user) => {
    assert(canManageSettings(user), "Only admins can manage the product catalogue.");
    const existing = await prisma.product.findFirst({ where: { id, orgId: user.orgId } });
    if (!existing) fail("Product not found.");
    await prisma.product.update({ where: { id }, data: { isActive } });
    await audit({ orgId: user.orgId, actorId: user.id, action: isActive ? "activate" : "deactivate", entity: "Product", entityId: id, entityLabel: existing.name });
    revalidatePath("/products");
  });
}

export async function deleteProduct(id: string) {
  return act(async (user) => {
    assert(canManageSettings(user), "Only admins can manage the product catalogue.");
    const existing = await prisma.product.findFirst({ where: { id, orgId: user.orgId }, include: { _count: { select: { lineItems: true } } } });
    if (!existing) fail("Product not found.");
    if (existing._count.lineItems > 0) fail(`This product is on ${existing._count.lineItems} deal(s). Mark it inactive instead.`);
    await prisma.product.delete({ where: { id } });
    await audit({ orgId: user.orgId, actorId: user.id, action: "delete", entity: "Product", entityId: id, entityLabel: existing.name, before: existing });
    revalidatePath("/products");
  });
}

export async function searchProducts(q: string) {
  return act(async (user) => {
    const rows = await prisma.product.findMany({
      where: { orgId: user.orgId, isActive: true, OR: [{ name: { contains: q, mode: "insensitive" } }, { sku: { contains: q, mode: "insensitive" } }] },
      orderBy: { name: "asc" },
      take: 10,
    });
    return rows.map((p) => ({ id: p.id, name: p.name, sku: p.sku, price: toNumber(p.price), taxRate: toNumber(p.taxRate) }));
  });
}

const lineItemSchema = z.object({
  productId: z.string().nullable(),
  name: z.string().trim().min(1, "Line item needs a name").max(160),
  quantity: z.coerce.number().min(0).max(1e9),
  unitPrice: z.coerce.number().min(0).max(1e12),
  discountPercent: z.coerce.number().min(0).max(100),
  taxRate: z.coerce.number().min(0).max(100),
});

export type LineItemInput = z.infer<typeof lineItemSchema>;

function computeLineTotal(li: { quantity: number; unitPrice: number; discountPercent: number; taxRate: number }) {
  const gross = li.quantity * li.unitPrice;
  const net = gross * (1 - li.discountPercent / 100);
  return Math.round(net * (1 + li.taxRate / 100) * 100) / 100;
}

export async function saveDealLineItems(dealId: string, items: LineItemInput[], amountFromItems: boolean) {
  return act(async (user) => {
    const deal = await prisma.deal.findFirst({ where: { id: dealId, orgId: user.orgId } });
    if (!deal) fail("Deal not found.");
    assert(await canEditRecord(user, "DEAL", deal));
    const parsed = z.array(lineItemSchema).max(100).parse(items);
    const productIds = parsed.map((i) => i.productId).filter((x): x is string => !!x);
    if (productIds.length) {
      const known = await prisma.product.findMany({ where: { orgId: user.orgId, id: { in: productIds } }, select: { id: true } });
      if (known.length !== new Set(productIds).size) fail("One of the products no longer exists.");
    }
    const rows = parsed.map((i, idx) => ({ ...i, total: computeLineTotal(i), position: idx }));
    const sum = Math.round(rows.reduce((s, r) => s + r.total, 0) * 100) / 100;
    await prisma.$transaction([
      prisma.dealLineItem.deleteMany({ where: { dealId } }),
      prisma.dealLineItem.createMany({ data: rows.map((r) => ({ ...r, dealId })) }),
      prisma.deal.update({
        where: { id: dealId },
        data: { amountIsManual: !amountFromItems, ...(amountFromItems ? { amount: sum } : {}) },
      }),
    ]);
    await audit({ orgId: user.orgId, actorId: user.id, action: "update_line_items", entity: "DEAL", entityId: dealId, entityLabel: deal.title, after: { items: rows.length, subtotal: sum, amountFromItems } });
    revalidatePath(`/deals/${dealId}`);
    revalidatePath("/deals");
    return { sum };
  });
}
