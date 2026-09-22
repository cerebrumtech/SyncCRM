"use client";

import { useState, useTransition } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { CheckSquare, PhoneCall, CalendarDays, Repeat, Trash2, Pencil, Undo2 } from "lucide-react";
import { Avatar } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { ActivityFormModal, type ActivityFormValues } from "./activity-form";
import { deleteActivity, setActivityStatus } from "@/lib/actions/activities";
import { formatDate, formatDateTime } from "@/lib/format";
import { cn } from "@/lib/cn";

export type ActivityRow = ActivityFormValues & {
  id: string;
  type: "TASK" | "CALL" | "EVENT";
  title: string;
  status: "OPEN" | "COMPLETED" | "CANCELLED";
  dueAtIso: string | null;
  allDay: boolean;
  assignee: { id: string; name: string; color: string } | null;
  canEdit: boolean;
};

const ICON = { TASK: CheckSquare, CALL: PhoneCall, EVENT: CalendarDays };

export function ActivityList({ items, users, meId, focusId, showLinks = true, emptyText = "No activities." }: { items: ActivityRow[]; users: { id: string; name: string }[]; meId: string; focusId?: string; showLinks?: boolean; emptyText?: string }) {
  const router = useRouter();
  const [editing, setEditing] = useState<ActivityRow | null>(items.find((i) => i.id === focusId) ?? null);
  const [pending, start] = useTransition();
  const [now] = useState(() => Date.now());

  if (items.length === 0) return <p className="text-sm text-ink-500">{emptyText}</p>;

  return (
    <>
      <ul className="divide-y divide-line-100">
        {items.map((a) => {
          const Icon = ICON[a.type];
          const overdue = a.status === "OPEN" && a.dueAtIso && new Date(a.dueAtIso).getTime() < now;
          const done = a.status === "COMPLETED";
          return (
            <li key={a.id} className={cn("flex items-start gap-3 py-2.5", a.status === "CANCELLED" && "opacity-60")}>
              <button
                type="button"
                disabled={!a.canEdit || pending}
                title={done ? "Mark as open" : "Mark as completed"}
                onClick={() => start(async () => { await setActivityStatus(a.id, done ? "OPEN" : "COMPLETED"); router.refresh(); })}
                className={cn("mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded border", done ? "border-success bg-success text-white" : "border-line bg-white text-transparent hover:border-primary")}
              >
                ✓
              </button>
              <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                  <Icon size={14} className="text-ink-500" />
                  <button type="button" className={cn("text-left text-sm font-medium hover:text-primary", done && "text-ink-500 line-through")} onClick={() => a.canEdit && setEditing(a)}>
                    {a.title}
                  </button>
                  {a.recurrence && a.recurrence !== "NONE" && <Repeat size={12} className="text-ink-500" />}
                  {a.status === "CANCELLED" && <Badge tone="neutral">Cancelled</Badge>}
                  {overdue && <Badge tone="danger">Overdue</Badge>}
                  {a.type === "CALL" && a.callDirection && <Badge tone="info">{a.callDirection === "INBOUND" ? "Inbound" : "Outbound"}{a.callDurationMin ? ` · ${a.callDurationMin} min` : ""}</Badge>}
                </div>
                <p className="mt-0.5 text-xs text-ink-500">
                  {a.dueAtIso ? (a.allDay ? formatDate(a.dueAtIso) : formatDateTime(a.dueAtIso)) : "No date"}
                  {a.endAt && ` – ${a.endAt.slice(11, 16)}`}
                  {a.location && ` · ${a.location}`}
                  {a.callOutcome && ` · ${a.callOutcome}`}
                  {showLinks && a.contact && (
                    <>
                      {" · "}
                      <Link href={`/contacts/${a.contact.id}`} className="hover:text-primary">{a.contact.label}</Link>
                    </>
                  )}
                  {showLinks && a.company && (
                    <>
                      {" · "}
                      <Link href={`/companies/${a.company.id}`} className="hover:text-primary">{a.company.label}</Link>
                    </>
                  )}
                  {showLinks && a.deal && (
                    <>
                      {" · "}
                      <Link href={`/deals/${a.deal.id}`} className="hover:text-primary">{a.deal.label}</Link>
                    </>
                  )}
                </p>
                {a.description && <p className="mt-1 whitespace-pre-wrap text-xs text-ink-700">{a.description}</p>}
              </div>
              {a.assignee && <Avatar name={a.assignee.name} color={a.assignee.color} size={24} />}
              {a.canEdit && (
                <div className="flex items-center gap-0.5">
                  <button type="button" className="rounded p-1 text-ink-500 hover:bg-surface hover:text-ink" title="Edit" onClick={() => setEditing(a)}>
                    <Pencil size={13} />
                  </button>
                  {a.status !== "CANCELLED" ? (
                    <button type="button" className="rounded p-1 text-ink-500 hover:bg-surface hover:text-ink" title="Cancel" disabled={pending} onClick={() => start(async () => { await setActivityStatus(a.id, "CANCELLED"); router.refresh(); })}>
                      <Undo2 size={13} />
                    </button>
                  ) : null}
                  <button type="button" className="rounded p-1 text-ink-500 hover:bg-surface hover:text-danger" title="Delete" disabled={pending} onClick={() => { if (confirm("Delete this activity?")) start(async () => { await deleteActivity(a.id); router.refresh(); }); }}>
                    <Trash2 size={13} />
                  </button>
                </div>
              )}
            </li>
          );
        })}
      </ul>
      {editing && <ActivityFormModal open onClose={() => setEditing(null)} values={editing} users={users} meId={meId} />}
    </>
  );
}
