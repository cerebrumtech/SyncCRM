"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { Bookmark, BookmarkPlus, Trash2, Download } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Checkbox, Field, Input } from "@/components/ui/input";
import { Modal, ModalFooter } from "@/components/ui/modal";
import { FormError } from "@/components/ui/form";
import { deleteView, saveView } from "@/lib/actions/views";
import { cn } from "@/lib/cn";

export type SavedViewItem = { id: string; name: string; filters: Record<string, string>; isShared: boolean; ownerId: string; ownerName: string };

export function SavedViews({ entity, views, meId, isAdmin, exportHref }: { entity: "CONTACT" | "COMPANY" | "DEAL"; views: SavedViewItem[]; meId: string; isAdmin: boolean; exportHref: string }) {
  const router = useRouter();
  const pathname = usePathname();
  const sp = useSearchParams();
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string>();
  const [pending, start] = useTransition();
  const current = Object.fromEntries([...sp.entries()].filter(([k, v]) => v && k !== "page"));
  const hasFilters = Object.keys(current).length > 0;
  const activeId = views.find((v) => sameFilters(v.filters, current))?.id;

  return (
    <div className="mb-4 flex flex-wrap items-center gap-2">
      <Link href={pathname} className={cn("rounded-full border px-3 py-1 text-xs font-medium", !hasFilters ? "border-navy bg-navy text-white" : "border-line bg-white text-ink-700 hover:border-primary")}>
        All
      </Link>
      {views.map((v) => (
        <span key={v.id} className={cn("group inline-flex items-center gap-1 rounded-full border pl-3 pr-1 text-xs font-medium", activeId === v.id ? "border-primary bg-primary text-white" : "border-line bg-white text-ink-700 hover:border-primary")}>
          <Link href={`${pathname}?${new URLSearchParams(v.filters)}`} className="py-1" title={v.isShared ? `Shared by ${v.ownerName}` : "Private view"}>
            <Bookmark size={11} className="mr-1 inline" />
            {v.name}
          </Link>
          {(v.ownerId === meId || isAdmin) && (
            <button
              type="button"
              className="rounded-full p-1 opacity-0 hover:bg-black/10 group-hover:opacity-100"
              title="Delete view"
              disabled={pending}
              onClick={() => {
                if (confirm(`Delete view "${v.name}"?`)) start(async () => { await deleteView(v.id); router.refresh(); });
              }}
            >
              <Trash2 size={11} />
            </button>
          )}
        </span>
      ))}
      {hasFilters && !activeId && (
        <Button variant="ghost" size="sm" onClick={() => setSaving(true)}>
          <BookmarkPlus size={14} /> Save this view
        </Button>
      )}
      <a href={`${exportHref}${exportHref.includes("?") ? "&" : "?"}${sp.toString()}`} className="ml-auto inline-flex h-8 items-center gap-1.5 rounded-md border border-line bg-white px-3 text-[13px] font-medium text-ink hover:bg-surface" title="Download the current list as CSV">
        <Download size={14} /> Export CSV
      </a>
      <Modal open={saving} onClose={() => setSaving(false)} title="Save view" size="sm">
        <form
          action={(fd) =>
            start(async () => {
              const res = await saveView(entity, String(fd.get("name") ?? ""), current, fd.get("shared") === "on");
              if (!res.ok) return setError(res.error);
              setSaving(false);
              router.refresh();
            })
          }
          className="space-y-3"
        >
          <FormError message={error} />
          <Field label="Name" htmlFor="view-name" required>
            <Input id="view-name" name="name" placeholder="e.g. My Pune leads" required autoFocus />
          </Field>
          <p className="text-xs text-ink-500">Saves the current search and filters: {Object.entries(current).map(([k, v]) => `${k}=${v}`).join(", ")}</p>
          <label className="flex items-center gap-2 text-sm">
            <Checkbox name="shared" /> Share with the whole team
          </label>
          <ModalFooter>
            <Button variant="secondary" onClick={() => setSaving(false)}>
              Cancel
            </Button>
            <Button type="submit" disabled={pending}>
              Save view
            </Button>
          </ModalFooter>
        </form>
      </Modal>
    </div>
  );
}

function sameFilters(a: Record<string, string>, b: Record<string, string>) {
  const ka = Object.keys(a).sort();
  const kb = Object.keys(b).sort();
  return ka.length === kb.length && ka.every((k, i) => k === kb[i] && a[k] === b[k]);
}
