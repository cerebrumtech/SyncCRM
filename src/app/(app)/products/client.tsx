"use client";

import { useState, useTransition } from "react";
import { Plus, Pencil, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Checkbox, Field, Input, Textarea } from "@/components/ui/input";
import { Modal, ModalFooter } from "@/components/ui/modal";
import { FormError } from "@/components/ui/form";
import { createProduct, deleteProduct, setProductActive, updateProduct } from "@/lib/actions/products";

export type ProductRow = {
  id: string;
  name: string;
  sku: string | null;
  description: string | null;
  price: number;
  taxRate: number;
  imageUrl: string | null;
  isActive: boolean;
  usage: number;
};

export function NewProductButton() {
  const [open, setOpen] = useState(false);
  return (
    <>
      <Button onClick={() => setOpen(true)}>
        <Plus size={16} /> New product
      </Button>
      {open && <ProductModal onClose={() => setOpen(false)} />}
    </>
  );
}

export function ProductRowActions({ product }: { product: ProductRow }) {
  const [editing, setEditing] = useState(false);
  const [pending, start] = useTransition();
  return (
    <div className="flex justify-end gap-1">
      <Button variant="ghost" size="sm" onClick={() => setEditing(true)}>
        <Pencil size={14} /> Edit
      </Button>
      <Button variant="ghost" size="sm" disabled={pending} onClick={() => start(async () => void (await setProductActive(product.id, !product.isActive)))}>
        {product.isActive ? "Deactivate" : "Activate"}
      </Button>
      {product.usage === 0 && (
        <Button
          variant="ghost"
          size="sm"
          className="text-danger"
          disabled={pending}
          onClick={() => {
            if (confirm(`Delete ${product.name}?`))
              start(async () => {
                const res = await deleteProduct(product.id);
                if (!res.ok) alert(res.error);
              });
          }}
        >
          <Trash2 size={14} />
        </Button>
      )}
      {editing && <ProductModal product={product} onClose={() => setEditing(false)} />}
    </div>
  );
}

function ProductModal({ product, onClose }: { product?: ProductRow; onClose: () => void }) {
  const [error, setError] = useState<string>();
  const [pending, start] = useTransition();
  return (
    <Modal open onClose={onClose} title={product ? "Edit product" : "New product"}>
      <form
        onSubmit={(e) => {
          e.preventDefault();
          const fd = new FormData(e.currentTarget);
          if (!fd.get("isActive")) fd.set("isActive", "off");
          start(async () => {
            const res = product ? await updateProduct(product.id, fd) : await createProduct(fd);
            if (!res.ok) setError(res.error);
            else onClose();
          });
        }}
        className="space-y-4"
      >
        <FormError message={error} />
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Name" htmlFor="p-name" required className="sm:col-span-2">
            <Input id="p-name" name="name" defaultValue={product?.name ?? ""} required autoFocus />
          </Field>
          <Field label="SKU" htmlFor="p-sku">
            <Input id="p-sku" name="sku" defaultValue={product?.sku ?? ""} placeholder="e.g. LMS-STD-1Y" />
          </Field>
          <Field label="Image URL" htmlFor="p-image">
            <Input id="p-image" name="imageUrl" defaultValue={product?.imageUrl ?? ""} placeholder="https://" />
          </Field>
          <Field label="Price (₹)" htmlFor="p-price" required>
            <Input id="p-price" name="price" type="number" min={0} step="0.01" defaultValue={product?.price ?? ""} required />
          </Field>
          <Field label="Tax rate (%)" htmlFor="p-tax" hint="GST, e.g. 18">
            <Input id="p-tax" name="taxRate" type="number" min={0} max={100} step="0.01" defaultValue={product?.taxRate ?? 18} />
          </Field>
          <Field label="Description" htmlFor="p-desc" className="sm:col-span-2">
            <Textarea id="p-desc" name="description" defaultValue={product?.description ?? ""} />
          </Field>
          <label className="flex items-center gap-2 text-sm">
            <Checkbox name="isActive" defaultChecked={product?.isActive ?? true} /> Active (can be added to new deals)
          </label>
        </div>
        <ModalFooter>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" disabled={pending}>
            {pending ? "Saving…" : product ? "Save changes" : "Create product"}
          </Button>
        </ModalFooter>
      </form>
    </Modal>
  );
}
