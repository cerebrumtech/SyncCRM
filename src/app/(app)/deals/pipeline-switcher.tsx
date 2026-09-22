"use client";

import { useRouter, useSearchParams } from "next/navigation";

export function PipelineSwitcher({ pipelines, currentId }: { pipelines: { id: string; name: string }[]; currentId: string }) {
  const router = useRouter();
  const sp = useSearchParams();
  return (
    <select
      className="h-9 rounded-md border border-line bg-white px-3 text-sm font-medium text-ink"
      value={currentId}
      aria-label="Pipeline"
      onChange={(e) => {
        const next = new URLSearchParams(sp.toString());
        next.set("pipeline", e.target.value);
        next.delete("stage");
        router.push(`/deals?${next}`);
      }}
    >
      {pipelines.map((p) => (
        <option key={p.id} value={p.id}>
          {p.name}
        </option>
      ))}
    </select>
  );
}
