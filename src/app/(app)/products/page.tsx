import { requireUser } from "@/lib/auth";
import { prisma } from "@/lib/db";
import { canManageSettings } from "@/lib/permissions";
import { formatINR, toNumber } from "@/lib/format";
import { Card, EmptyState, PageHeader } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Input, Select } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { NewProductButton, ProductRowActions } from "./client";
import type { Prisma } from "@/generated/prisma/client";

export const metadata = { title: "Products" };

export default async function ProductsPage(props: PageProps<"/products">) {
  const me = await requireUser();
  const sp = await props.searchParams;
  const q = typeof sp.q === "string" ? sp.q.trim() : "";
  const status = sp.status === "inactive" ? "inactive" : sp.status === "all" ? "all" : "active";
  const canManage = canManageSettings(me);

  const where: Prisma.ProductWhereInput = {
    orgId: me.orgId,
    ...(status === "active" ? { isActive: true } : status === "inactive" ? { isActive: false } : {}),
    ...(q ? { OR: [{ name: { contains: q, mode: "insensitive" } }, { sku: { contains: q, mode: "insensitive" } }] } : {}),
  };
  const products = await prisma.product.findMany({ where, orderBy: { name: "asc" }, include: { _count: { select: { lineItems: true } } } });

  return (
    <>
      <PageHeader title="Products" subtitle="Catalogue of products and services you sell. Add them to deals as line items." actions={canManage ? <NewProductButton /> : undefined} />
      <form className="mb-4 flex flex-wrap items-center gap-2">
        <Input name="q" placeholder="Search name or SKU…" defaultValue={q} className="w-64" />
        <Select name="status" defaultValue={status} className="w-36">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
          <option value="all">All</option>
        </Select>
        <Button type="submit" variant="secondary">
          Apply
        </Button>
      </form>
      {products.length === 0 ? (
        <EmptyState title="No products" description={canManage ? "Add your first product or service." : "Ask an administrator to add products."} />
      ) : (
        <Card>
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-navy text-left text-[13px] font-semibold text-white">
                <th className="px-4 py-2.5">Product</th>
                <th className="px-4 py-2.5">SKU</th>
                <th className="px-4 py-2.5 text-right">Price</th>
                <th className="px-4 py-2.5 text-right">Tax</th>
                <th className="px-4 py-2.5 text-right">On deals</th>
                <th className="px-4 py-2.5">Status</th>
                {canManage && <th className="px-4 py-2.5 text-right">Actions</th>}
              </tr>
            </thead>
            <tbody>
              {products.map((p, i) => (
                <tr key={p.id} className={i % 2 ? "bg-white" : "bg-page"}>
                  <td className="px-4 py-2.5">
                    <div className="flex items-center gap-3">
                      {p.imageUrl ? (
                        // eslint-disable-next-line @next/next/no-img-element
                        <img src={p.imageUrl} alt="" className="h-9 w-9 rounded object-cover" />
                      ) : (
                        <span className="flex h-9 w-9 items-center justify-center rounded bg-surface text-xs font-semibold text-ink-500">{p.name.slice(0, 2).toUpperCase()}</span>
                      )}
                      <div>
                        <p className="font-medium">{p.name}</p>
                        {p.description && <p className="max-w-md truncate text-xs text-ink-500">{p.description}</p>}
                      </div>
                    </div>
                  </td>
                  <td className="px-4 py-2.5 font-mono text-xs">{p.sku ?? "—"}</td>
                  <td className="px-4 py-2.5 text-right tabular font-semibold">{formatINR(toNumber(p.price), true)}</td>
                  <td className="px-4 py-2.5 text-right tabular">{toNumber(p.taxRate)}%</td>
                  <td className="px-4 py-2.5 text-right tabular">{p._count.lineItems}</td>
                  <td className="px-4 py-2.5">
                    <Badge tone={p.isActive ? "success" : "neutral"}>{p.isActive ? "Active" : "Inactive"}</Badge>
                  </td>
                  {canManage && (
                    <td className="px-4 py-2.5 text-right">
                      <ProductRowActions product={{ id: p.id, name: p.name, sku: p.sku, description: p.description, price: toNumber(p.price), taxRate: toNumber(p.taxRate), imageUrl: p.imageUrl, isActive: p.isActive, usage: p._count.lineItems }} />
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      )}
    </>
  );
}
