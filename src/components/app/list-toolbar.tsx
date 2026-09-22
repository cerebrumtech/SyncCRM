import { Search } from "lucide-react";
import { Input, Select } from "@/components/ui/input";
import { Button } from "@/components/ui/button";

export function ListToolbar({
  q,
  owners,
  ownerId,
  tags,
  tag,
  extra,
}: {
  q: string;
  owners: { id: string; name: string }[];
  ownerId?: string;
  tags: string[];
  tag?: string;
  extra?: React.ReactNode;
}) {
  return (
    <form className="mb-4 flex flex-wrap items-center gap-2">
      <div className="relative w-72">
        <Search size={15} className="pointer-events-none absolute left-2.5 top-2.5 text-ink-500" />
        <Input name="q" placeholder="Search…" defaultValue={q} className="pl-8" />
      </div>
      <Select name="owner" defaultValue={ownerId ?? ""} className="w-44">
        <option value="">All owners</option>
        {owners.map((o) => (
          <option key={o.id} value={o.id}>
            {o.name}
          </option>
        ))}
      </Select>
      {tags.length > 0 && (
        <Select name="tag" defaultValue={tag ?? ""} className="w-40">
          <option value="">All tags</option>
          {tags.map((t) => (
            <option key={t} value={t}>
              {t}
            </option>
          ))}
        </Select>
      )}
      {extra}
      <Button type="submit" variant="secondary">
        Apply
      </Button>
    </form>
  );
}

export function Pagination({ page, pages, params }: { page: number; pages: number; params: Record<string, string | undefined> }) {
  if (pages <= 1) return null;
  const link = (p: number) => {
    const sp = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) if (v) sp.set(k, v);
    sp.set("page", String(p));
    return `?${sp}`;
  };
  return (
    <div className="flex items-center justify-between border-t border-line-100 px-4 py-2 text-xs text-ink-500">
      <span>
        Page {page} of {pages}
      </span>
      <div className="flex gap-1">
        {page > 1 && (
          <Button href={link(page - 1)} variant="secondary" size="sm">
            Previous
          </Button>
        )}
        {page < pages && (
          <Button href={link(page + 1)} variant="secondary" size="sm">
            Next
          </Button>
        )}
      </div>
    </div>
  );
}
