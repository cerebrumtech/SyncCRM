"use client";

import { useState, type ReactNode } from "react";
import { cn } from "@/lib/cn";

export function Tabs({
  tabs,
  initial,
}: {
  tabs: { key: string; label: string; count?: number; content: ReactNode }[];
  initial?: string;
}) {
  const [active, setActive] = useState(initial ?? tabs[0]?.key);
  const current = tabs.find((t) => t.key === active) ?? tabs[0];
  return (
    <div>
      <div className="flex gap-1 border-b border-line-100">
        {tabs.map((t) => (
          <button
            key={t.key}
            type="button"
            onClick={() => setActive(t.key)}
            className={cn(
              "-mb-px border-b-2 px-3 py-2 text-sm font-medium",
              t.key === current?.key ? "border-primary text-primary" : "border-transparent text-ink-700 hover:text-ink",
            )}
          >
            {t.label}
            {typeof t.count === "number" && (
              <span className="ml-1.5 rounded-full bg-surface px-1.5 text-xs text-ink-500">{t.count}</span>
            )}
          </button>
        ))}
      </div>
      <div className="pt-4">{current?.content}</div>
    </div>
  );
}
