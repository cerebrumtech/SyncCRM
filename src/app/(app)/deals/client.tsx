"use client";

import { useMemo, useState, useTransition } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { DndContext, DragOverlay, PointerSensor, useDraggable, useDroppable, useSensor, useSensors, type DragEndEvent, type DragStartEvent } from "@dnd-kit/core";
import { Plus, Building2, User, CalendarDays } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Avatar } from "@/components/ui/avatar";
import { Modal, ModalFooter } from "@/components/ui/modal";
import { Field, Select, Input } from "@/components/ui/input";
import { DealFormModal, type PipelineOption } from "@/components/app/deal-form";
import { moveDeal } from "@/lib/actions/deals";
import { formatCompactINR, formatDate, formatINR } from "@/lib/format";
import { LOST_REASONS } from "@/lib/defaults";
import { cn } from "@/lib/cn";
import type { FieldDef } from "@/components/app/fields";

export type BoardDeal = {
  id: string;
  title: string;
  stageId: string;
  status: "OPEN" | "WON" | "LOST";
  amount: number;
  expectedCloseDate: string | null;
  contact: { id: string; name: string } | null;
  company: { id: string; name: string } | null;
  owner: { id: string; name: string; color: string } | null;
  position: number;
};

type StageCol = { id: string; name: string; color: string; kind: "OPEN" | "WON" | "LOST"; probability: number };

export function NewDealButton(props: {
  pipelines: PipelineOption[];
  users: { id: string; name: string }[];
  fieldDefs: FieldDef[];
  tagSuggestions: string[];
  meId: string;
  defaultPipelineId: string;
  autoOpen?: { contactId?: string; companyId?: string } | null;
}) {
  const [open, setOpen] = useState(!!props.autoOpen);
  return (
    <>
      <Button onClick={() => setOpen(true)}>
        <Plus size={16} /> New deal
      </Button>
      {open && (
        <DealFormModal
          open
          onClose={() => setOpen(false)}
          pipelines={props.pipelines}
          users={props.users}
          fieldDefs={props.fieldDefs}
          tagSuggestions={props.tagSuggestions}
          meId={props.meId}
          values={{ pipelineId: props.defaultPipelineId, contactId: props.autoOpen?.contactId, companyId: props.autoOpen?.companyId }}
        />
      )}
    </>
  );
}

export function LostReasonModal({ open, onClose, onSubmit, pending }: { open: boolean; onClose: () => void; onSubmit: (reason: string) => void; pending?: boolean }) {
  const [reason, setReason] = useState(LOST_REASONS[0]!);
  const [other, setOther] = useState("");
  return (
    <Modal open={open} onClose={onClose} title="Why was this deal lost?" size="sm">
      <div className="space-y-3">
        <Field label="Lost reason" htmlFor="lost-reason">
          <Select id="lost-reason" value={reason} onChange={(e) => setReason(e.target.value)}>
            {LOST_REASONS.map((r) => (
              <option key={r} value={r}>
                {r}
              </option>
            ))}
          </Select>
        </Field>
        {reason === "Other" && (
          <Field label="Details" htmlFor="lost-other">
            <Input id="lost-other" value={other} onChange={(e) => setOther(e.target.value)} placeholder="Tell the team what happened" />
          </Field>
        )}
        <ModalFooter>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button variant="danger" disabled={pending} onClick={() => onSubmit(reason === "Other" && other.trim() ? `Other: ${other.trim()}` : reason)}>
            Mark as lost
          </Button>
        </ModalFooter>
      </div>
    </Modal>
  );
}

export function DealBoard({ pipelineId, stages, deals: initial }: { pipelineId: string; stages: StageCol[]; deals: BoardDeal[] }) {
  const router = useRouter();
  const [deals, setDeals] = useState(initial);
  const [active, setActive] = useState<BoardDeal | null>(null);
  const [error, setError] = useState<string>();
  const [pendingLost, setPendingLost] = useState<{ dealId: string; stageId: string; revert: BoardDeal[] } | null>(null);
  const [, start] = useTransition();
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }));

  const [prevInitial, setPrevInitial] = useState(initial);
  if (prevInitial !== initial) {
    setPrevInitial(initial);
    setDeals(initial);
  }

  const byStage = useMemo(() => {
    const m = new Map<string, BoardDeal[]>();
    for (const s of stages) m.set(s.id, []);
    for (const d of deals) m.get(d.stageId)?.push(d);
    for (const list of m.values()) list.sort((a, b) => a.position - b.position);
    return m;
  }, [deals, stages]);

  function apply(dealId: string, stageId: string, reason?: string, revertTo?: BoardDeal[]) {
    const snapshot = revertTo ?? deals;
    setDeals((cur) => cur.map((d) => (d.id === dealId ? { ...d, stageId, position: 1e9 } : d)));
    start(async () => {
      const res = await moveDeal(dealId, stageId, null, reason ?? null);
      if (!res.ok) {
        setDeals(snapshot);
        setError(res.error);
        return;
      }
      if (res.data?.missing?.length) {
        setDeals(snapshot);
        const stageName = stages.find((s) => s.id === stageId)?.name;
        setError(`To move into "${stageName}" this deal needs: ${res.data.missing.join(", ")}. Open the deal to fill them in.`);
        return;
      }
      if (res.data?.needsLostReason) {
        setPendingLost({ dealId, stageId, revert: snapshot });
        return;
      }
      setError(undefined);
      router.refresh();
    });
  }

  function onDragEnd(e: DragEndEvent) {
    setActive(null);
    const dealId = String(e.active.id);
    const stageId = e.over ? String(e.over.id) : null;
    const deal = deals.find((d) => d.id === dealId);
    if (!deal || !stageId || deal.stageId === stageId) return;
    apply(dealId, stageId);
  }

  return (
    <div className="flex min-h-0 flex-1 flex-col">
      {error && (
        <div className="mb-3 flex items-start justify-between rounded-md border border-danger/30 bg-danger-bg px-3 py-2 text-sm text-danger-fg">
          <span>{error}</span>
          <button type="button" className="ml-3 text-xs underline" onClick={() => setError(undefined)}>
            Dismiss
          </button>
        </div>
      )}
      <DndContext id={`board-${pipelineId}`} sensors={sensors} onDragStart={(e: DragStartEvent) => setActive(deals.find((d) => d.id === e.active.id) ?? null)} onDragEnd={onDragEnd} onDragCancel={() => setActive(null)}>
        <div className="scroll-thin flex min-h-0 flex-1 gap-3 overflow-x-auto pb-3">
          {stages.map((s) => (
            <Column key={s.id} stage={s} deals={byStage.get(s.id) ?? []} />
          ))}
        </div>
        <DragOverlay>{active ? <DealCard deal={active} overlay /> : null}</DragOverlay>
      </DndContext>
      <LostReasonModal
        open={!!pendingLost}
        onClose={() => {
          if (pendingLost) setDeals(pendingLost.revert);
          setPendingLost(null);
        }}
        onSubmit={(reason) => {
          const p = pendingLost!;
          setPendingLost(null);
          apply(p.dealId, p.stageId, reason, p.revert);
        }}
      />
      <span className="sr-only">{pipelineId}</span>
    </div>
  );
}

function Column({ stage, deals }: { stage: StageCol; deals: BoardDeal[] }) {
  const { setNodeRef, isOver } = useDroppable({ id: stage.id });
  const total = deals.reduce((s, d) => s + d.amount, 0);
  return (
    <div ref={setNodeRef} className={cn("flex w-72 shrink-0 flex-col rounded-[10px] border bg-surface/60", isOver ? "border-primary bg-primary-50" : "border-line-100")}>
      <div className="flex items-center justify-between px-3 py-2.5">
        <div className="flex items-center gap-2">
          <span className="h-2.5 w-2.5 rounded-full" style={{ background: stage.color }} />
          <span className="text-sm font-semibold text-ink">{stage.name}</span>
          <span className="rounded-full bg-white px-1.5 text-xs text-ink-500">{deals.length}</span>
        </div>
        <span className="text-xs font-medium tabular text-ink-700">{formatCompactINR(total)}</span>
      </div>
      <div className="scroll-thin flex-1 space-y-2 overflow-y-auto px-2 pb-2">
        {deals.map((d) => (
          <DraggableCard key={d.id} deal={d} />
        ))}
        {deals.length === 0 && <p className="px-2 py-6 text-center text-xs text-ink-500">Drop deals here</p>}
      </div>
    </div>
  );
}

function DraggableCard({ deal }: { deal: BoardDeal }) {
  const { attributes, listeners, setNodeRef, isDragging } = useDraggable({ id: deal.id });
  return (
    <div ref={setNodeRef} {...listeners} {...attributes} className={cn(isDragging && "opacity-30")}>
      <DealCard deal={deal} />
    </div>
  );
}

function DealCard({ deal, overlay }: { deal: BoardDeal; overlay?: boolean }) {
  const overdue = deal.status === "OPEN" && deal.expectedCloseDate && deal.expectedCloseDate < new Date().toISOString().slice(0, 10);
  return (
    <div className={cn("cursor-grab rounded-md border border-line-100 bg-white p-3 shadow-sm active:cursor-grabbing", overlay && "rotate-1 shadow-lg")}>
      <div className="flex items-start justify-between gap-2">
        <Link href={`/deals/${deal.id}`} className="text-sm font-medium leading-snug text-ink hover:text-primary" onPointerDown={(e) => e.stopPropagation()}>
          {deal.title}
        </Link>
        {deal.owner && <Avatar name={deal.owner.name} color={deal.owner.color} size={22} />}
      </div>
      <p className="mt-1.5 text-sm font-semibold tabular text-navy">{formatINR(deal.amount)}</p>
      <div className="mt-1.5 space-y-0.5 text-xs text-ink-500">
        {deal.contact && (
          <p className="flex items-center gap-1 truncate">
            <User size={11} /> {deal.contact.name}
          </p>
        )}
        {deal.company && (
          <p className="flex items-center gap-1 truncate">
            <Building2 size={11} /> {deal.company.name}
          </p>
        )}
        {deal.expectedCloseDate && (
          <p className={cn("flex items-center gap-1", overdue && "font-medium text-danger")}>
            <CalendarDays size={11} /> {formatDate(deal.expectedCloseDate)}
          </p>
        )}
      </div>
    </div>
  );
}
