"use client";

import { useState } from "react";
import Link from "next/link";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { Card } from "@/components/ui/card";
import { ActivityFormModal } from "@/components/app/activity-form";
import type { ActivityRow } from "@/components/app/activity-list";
import { cn } from "@/lib/cn";

const DOW = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
const COLOR = { TASK: "bg-info-bg text-info-fg", CALL: "bg-success-bg text-success-fg", EVENT: "bg-warning-bg text-warning-fg" };

export function MonthCalendar({ month, todayKey, items, users, meId }: { month: string; todayKey: string; items: ActivityRow[]; users: { id: string; name: string }[]; meId: string }) {
  const [editing, setEditing] = useState<ActivityRow | null>(null);
  const [y, m] = month.split("-").map(Number) as [number, number];
  const first = new Date(Date.UTC(y, m - 1, 1));
  const daysInMonth = new Date(Date.UTC(y, m, 0)).getUTCDate();
  const lead = (first.getUTCDay() + 6) % 7; // Monday-first
  const cells: (number | null)[] = [...Array(lead).fill(null), ...Array.from({ length: daysInMonth }, (_, i) => i + 1)];
  while (cells.length % 7) cells.push(null);
  const prev = new Date(Date.UTC(y, m - 2, 1)).toISOString().slice(0, 7);
  const next = new Date(Date.UTC(y, m, 1)).toISOString().slice(0, 7);

  const byDay = new Map<string, ActivityRow[]>();
  for (const it of items) {
    if (!it.dueAt) continue;
    const key = it.dueAt.slice(0, 10);
    byDay.set(key, [...(byDay.get(key) ?? []), it]);
  }

  return (
    <Card>
      <div className="flex items-center justify-between border-b border-line-100 px-4 py-2.5">
        <Link href={`?view=calendar&month=${prev}`} className="rounded p-1 hover:bg-surface" aria-label="Previous month">
          <ChevronLeft size={18} />
        </Link>
        <h3 className="text-base font-semibold">{first.toLocaleDateString("en-GB", { month: "long", year: "numeric", timeZone: "UTC" })}</h3>
        <Link href={`?view=calendar&month=${next}`} className="rounded p-1 hover:bg-surface" aria-label="Next month">
          <ChevronRight size={18} />
        </Link>
      </div>
      <div className="grid grid-cols-7 border-b border-line-100 bg-page text-center text-xs font-semibold uppercase text-ink-500">
        {DOW.map((d) => (
          <div key={d} className="py-1.5">
            {d}
          </div>
        ))}
      </div>
      <div className="grid grid-cols-7">
        {cells.map((day, i) => {
          const key = day ? `${month}-${String(day).padStart(2, "0")}` : "";
          const list = day ? byDay.get(key) ?? [] : [];
          return (
            <div key={i} className={cn("min-h-28 border-b border-r border-line-100 p-1.5", !day && "bg-page", key === todayKey && "bg-primary-50")}>
              {day && <p className={cn("mb-1 text-xs font-medium", key === todayKey ? "text-primary" : "text-ink-500")}>{day}</p>}
              <div className="space-y-1">
                {list.slice(0, 4).map((a) => (
                  <button
                    key={a.id}
                    type="button"
                    onClick={() => a.canEdit && setEditing(a)}
                    className={cn("block w-full truncate rounded px-1.5 py-0.5 text-left text-[11px] font-medium", COLOR[a.type], a.status === "COMPLETED" && "line-through opacity-60")}
                    title={a.title}
                  >
                    {!a.allDay && a.dueAt && <span className="mr-1 tabular">{a.dueAt.slice(11, 16)}</span>}
                    {a.title}
                  </button>
                ))}
                {list.length > 4 && <p className="text-[11px] text-ink-500">+{list.length - 4} more</p>}
              </div>
            </div>
          );
        })}
      </div>
      {editing && <ActivityFormModal open onClose={() => setEditing(null)} values={editing} users={users} meId={meId} />}
    </Card>
  );
}
