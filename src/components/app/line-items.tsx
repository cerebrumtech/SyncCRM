"use client";

import { useEffect, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Plus, Trash2, PackageSearch } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Checkbox, Input } from "@/components/ui/input";
import { FormError } from "@/components/ui/form";
import { saveDealLineItems, searchProducts, type LineItemInput } from "@/lib/actions/products";
import { formatINR } from "@/lib/format";

type Row = LineItemInput & { key: string };
type ProductHit = { id: string; name: string; sku: string | null; price: number; taxRate: number };

function total(r: LineItemInput) {
  const gross = r.quantity * r.unitPrice;
  const net = gross * (1 - r.discountPercent / 100);
  return Math.round(net * (1 + r.taxRate / 100) * 100) / 100;
}

export function LineItemsPanel({ dealId, initial, amountIsManual, canEdit }: { dealId: string; initial: LineItemInput[]; amountIsManual: boolean; canEdit: boolean }) {
  const router = useRouter();
  const [rows, setRows] = useState<Row[]>(initial.map((i, idx) => ({ ...i, key: `r${idx}` })));
  // First line items on a deal drive the amount unless the user opts out.
  const [fromItems, setFromItems] = useState(initial.length === 0 ? true : !amountIsManual);
  const [dirty, setDirty] = useState(false);
  const [error, setError] = useState<string>();
  const [pending, start] = useTransition();
  const [picker, setPicker] = useState(false);
  const sum = Math.round(rows.reduce((s, r) => s + total(r), 0) * 100) / 100;
  const subtotal = rows.reduce((s, r) => s + r.quantity * r.unitPrice * (1 - r.discountPercent / 100), 0);

  function update(key: string, patch: Partial<LineItemInput>) {
    setRows((cur) => cur.map((r) => (r.key === key ? { ...r, ...patch } : r)));
    setDirty(true);
  }
  function addProduct(p: ProductHit) {
    setRows((cur) => [...cur, { key: `n${Date.now()}`, productId: p.id, name: p.name, quantity: 1, unitPrice: p.price, discountPercent: 0, taxRate: p.taxRate }]);
    setDirty(true);
    setPicker(false);
  }
  function addCustom() {
    setRows((cur) => [...cur, { key: `n${Date.now()}`, productId: null, name: "", quantity: 1, unitPrice: 0, discountPercent: 0, taxRate: 18 }]);
    setDirty(true);
  }
  function save() {
    setError(undefined);
    start(async () => {
      const res = await saveDealLineItems(
        dealId,
        rows.map((r) => ({ productId: r.productId, name: r.name, quantity: r.quantity, unitPrice: r.unitPrice, discountPercent: r.discountPercent, taxRate: r.taxRate })),
        fromItems,
      );
      if (!res.ok) return setError(res.error);
      setDirty(false);
      router.refresh();
    });
  }

  return (
    <div className="space-y-3">
      <FormError message={error} />
      {rows.length === 0 ? (
        <p className="text-sm text-ink-500">No line items. Add products from the catalogue or a custom line.</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-line-100 text-left text-xs font-semibold uppercase tracking-wide text-ink-500">
                <th className="py-2 pr-2">Item</th>
                <th className="w-20 py-2 pr-2 text-right">Qty</th>
                <th className="w-32 py-2 pr-2 text-right">Unit price</th>
                <th className="w-20 py-2 pr-2 text-right">Disc %</th>
                <th className="w-20 py-2 pr-2 text-right">Tax %</th>
                <th className="w-32 py-2 pr-2 text-right">Total</th>
                {canEdit && <th className="w-8" />}
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.key} className="border-b border-line-100">
                  <td className="py-1.5 pr-2">
                    {canEdit ? <Input value={r.name} onChange={(e) => update(r.key, { name: e.target.value })} placeholder="Item name" className="h-8" /> : r.name}
                    {r.productId && <span className="ml-1 text-[10px] text-ink-500">catalogue</span>}
                  </td>
                  <Num value={r.quantity} canEdit={canEdit} onChange={(v) => update(r.key, { quantity: v })} />
                  <Num value={r.unitPrice} canEdit={canEdit} onChange={(v) => update(r.key, { unitPrice: v })} money />
                  <Num value={r.discountPercent} canEdit={canEdit} onChange={(v) => update(r.key, { discountPercent: v })} />
                  <Num value={r.taxRate} canEdit={canEdit} onChange={(v) => update(r.key, { taxRate: v })} />
                  <td className="py-1.5 pr-2 text-right tabular font-medium">{formatINR(total(r), true)}</td>
                  {canEdit && (
                    <td className="py-1.5">
                      <button type="button" className="text-ink-500 hover:text-danger" onClick={() => { setRows((cur) => cur.filter((x) => x.key !== r.key)); setDirty(true); }} title="Remove">
                        <Trash2 size={14} />
                      </button>
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
            <tfoot>
              <tr className="text-sm">
                <td colSpan={5} className="py-2 pr-2 text-right text-ink-500">
                  Subtotal (after discount)
                </td>
                <td className="py-2 pr-2 text-right tabular">{formatINR(subtotal, true)}</td>
              </tr>
              <tr className="text-sm">
                <td colSpan={5} className="py-1 pr-2 text-right text-ink-500">
                  Tax
                </td>
                <td className="py-1 pr-2 text-right tabular">{formatINR(sum - subtotal, true)}</td>
              </tr>
              <tr className="text-base font-semibold">
                <td colSpan={5} className="py-2 pr-2 text-right">
                  Total
                </td>
                <td className="py-2 pr-2 text-right tabular text-navy">{formatINR(sum, true)}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      )}

      {canEdit && (
        <div className="flex flex-wrap items-center gap-2">
          <Button variant="secondary" size="sm" onClick={() => setPicker(true)}>
            <PackageSearch size={14} /> Add from catalogue
          </Button>
          <Button variant="secondary" size="sm" onClick={addCustom}>
            <Plus size={14} /> Custom line
          </Button>
          <label className="ml-auto flex items-center gap-2 text-sm text-ink-700">
            <Checkbox checked={fromItems} onChange={(e) => { setFromItems(e.target.checked); setDirty(true); }} />
            Deal amount = line item total
          </label>
          <Button size="sm" disabled={!dirty || pending} onClick={save}>
            {pending ? "Saving…" : "Save line items"}
          </Button>
        </div>
      )}
      {picker && <ProductPicker onPick={addProduct} onClose={() => setPicker(false)} />}
    </div>
  );
}

function Num({ value, canEdit, onChange, money }: { value: number; canEdit: boolean; onChange: (v: number) => void; money?: boolean }) {
  return (
    <td className="py-1.5 pr-2 text-right tabular">
      {canEdit ? (
        <Input type="number" min={0} step={money ? "0.01" : "any"} value={value} onChange={(e) => onChange(Number(e.target.value) || 0)} className="h-8 text-right" />
      ) : money ? (
        formatINR(value, true)
      ) : (
        value
      )}
    </td>
  );
}

function ProductPicker({ onPick, onClose }: { onPick: (p: ProductHit) => void; onClose: () => void }) {
  const [q, setQ] = useState("");
  const [hits, setHits] = useState<ProductHit[]>([]);
  useEffect(() => {
    let cancelled = false;
    const t = setTimeout(async () => {
      const res = await searchProducts(q);
      if (!cancelled && res.ok) setHits(res.data ?? []);
    }, 120);
    return () => {
      cancelled = true;
      clearTimeout(t);
    };
  }, [q]);
  return (
    <div className="rounded-md border border-line-100 bg-page p-3">
      <div className="flex items-center gap-2">
        <Input autoFocus placeholder="Search products by name or SKU…" value={q} onChange={(e) => setQ(e.target.value)} />
        <Button variant="ghost" size="sm" onClick={onClose}>
          Close
        </Button>
      </div>
      <ul className="mt-2 max-h-56 divide-y divide-line-100 overflow-auto rounded-md border border-line-100 bg-white">
        {hits.length === 0 && <li className="px-3 py-2 text-sm text-ink-500">No active products match.</li>}
        {hits.map((p) => (
          <li key={p.id}>
            <button type="button" className="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-surface" onClick={() => onPick(p)}>
              <span>
                {p.name} {p.sku && <span className="text-xs text-ink-500">· {p.sku}</span>}
              </span>
              <span className="tabular text-ink-700">
                {formatINR(p.price, true)} <span className="text-xs text-ink-500">+{p.taxRate}%</span>
              </span>
            </button>
          </li>
        ))}
      </ul>
    </div>
  );
}
