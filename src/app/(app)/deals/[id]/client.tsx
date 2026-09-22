"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { ArrowRightLeft, Check, Pencil, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Modal, ModalFooter } from "@/components/ui/modal";
import { Field, Select } from "@/components/ui/input";
import { FormError } from "@/components/ui/form";
import { DealFormModal, type DealFormValues, type PipelineOption } from "@/components/app/deal-form";
import { LostReasonModal } from "../client";
import { deleteDeal, handoffDeal, moveDeal } from "@/lib/actions/deals";
import { cn } from "@/lib/cn";
import type { FieldDef } from "@/components/app/fields";

export function StageStepper({ dealId, currentStageId, stages, canEdit }: { dealId: string; currentStageId: string; stages: { id: string; name: string; color: string; kind: "OPEN" | "WON" | "LOST" }[]; canEdit: boolean }) {
  const router = useRouter();
  const [pending, start] = useTransition();
  const [error, setError] = useState<string>();
  const [lostFor, setLostFor] = useState<string | null>(null);
  const currentIdx = stages.findIndex((s) => s.id === currentStageId);

  function go(stageId: string, reason?: string) {
    setError(undefined);
    start(async () => {
      const res = await moveDeal(dealId, stageId, null, reason ?? null);
      if (!res.ok) return setError(res.error);
      if (res.data?.missing?.length) return setError(`This stage needs: ${res.data.missing.join(", ")}. Edit the deal to fill them in.`);
      if (res.data?.needsLostReason) return setLostFor(stageId);
      router.refresh();
    });
  }

  return (
    <div>
      <ol className="flex flex-wrap gap-1">
        {stages.map((s, i) => {
          const done = i < currentIdx;
          const current = s.id === currentStageId;
          return (
            <li key={s.id} className="flex-1">
              <button
                type="button"
                disabled={!canEdit || pending || current}
                onClick={() => go(s.id)}
                className={cn(
                  "flex w-full items-center justify-center gap-1 rounded-md border px-2 py-1.5 text-xs font-medium transition disabled:cursor-default",
                  current ? "border-transparent text-white" : done ? "border-line-100 bg-surface text-ink-700" : "border-line-100 bg-white text-ink-700 hover:border-primary/50",
                  s.kind === "WON" && !current && "hover:border-success",
                  s.kind === "LOST" && !current && "hover:border-danger",
                )}
                style={current ? { background: s.color } : undefined}
                title={canEdit ? `Move to ${s.name}` : undefined}
              >
                {done && <Check size={12} />}
                {s.name}
              </button>
            </li>
          );
        })}
      </ol>
      {error && <p className="mt-2 text-sm text-danger">{error}</p>}
      <LostReasonModal open={!!lostFor} onClose={() => setLostFor(null)} pending={pending} onSubmit={(reason) => { const s = lostFor!; setLostFor(null); go(s, reason); }} />
    </div>
  );
}

export function DealActions({
  deal,
  status,
  canEdit,
  canDelete,
  pipelines,
  ...common
}: {
  deal: DealFormValues & { id: string; title: string; pipelineId: string };
  status: "OPEN" | "WON" | "LOST";
  canEdit: boolean;
  canDelete: boolean;
  pipelines: PipelineOption[];
  users: { id: string; name: string }[];
  fieldDefs: FieldDef[];
  tagSuggestions: string[];
  meId: string;
}) {
  const router = useRouter();
  const [editing, setEditing] = useState(false);
  const [handoff, setHandoff] = useState(false);
  const [target, setTarget] = useState(pipelines.find((p) => p.id !== deal.pipelineId)?.id ?? "");
  const [error, setError] = useState<string>();
  const [pending, start] = useTransition();
  const others = pipelines.filter((p) => p.id !== deal.pipelineId);

  return (
    <div className="flex items-center gap-2">
      {canEdit && (
        <Button variant="secondary" size="sm" onClick={() => setEditing(true)}>
          <Pencil size={14} /> Edit
        </Button>
      )}
      {canEdit && others.length > 0 && (
        <Button variant="secondary" size="sm" onClick={() => setHandoff(true)} title="Create a linked deal in another pipeline">
          <ArrowRightLeft size={14} /> Hand off
        </Button>
      )}
      {canDelete && (
        <Button
          variant="ghost"
          size="sm"
          className="text-danger"
          disabled={pending}
          onClick={() => {
            if (confirm(`Delete deal "${deal.title}"?`))
              start(async () => {
                const res = await deleteDeal(deal.id);
                if (!res.ok) alert(res.error);
              });
          }}
        >
          <Trash2 size={14} /> Delete
        </Button>
      )}
      {editing && <DealFormModal open onClose={() => setEditing(false)} values={deal} pipelines={pipelines} {...common} />}
      <Modal open={handoff} onClose={() => setHandoff(false)} title="Hand off to another pipeline" size="sm">
        <p className="text-sm text-ink-700">
          Creates a linked copy of <strong>{deal.title}</strong> in the first stage of the chosen pipeline, keeping the contact,
          company, owner and line items. {status !== "WON" && "Usually done once a deal is won."}
        </p>
        <div className="mt-4">
          <Field label="Target pipeline" htmlFor="handoff-target">
            <Select id="handoff-target" value={target} onChange={(e) => setTarget(e.target.value)}>
              {others.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </Select>
          </Field>
        </div>
        <div className="mt-3">
          <FormError message={error} />
        </div>
        <ModalFooter>
          <Button variant="secondary" onClick={() => setHandoff(false)}>
            Cancel
          </Button>
          <Button
            disabled={pending || !target}
            onClick={() =>
              start(async () => {
                const res = await handoffDeal(deal.id, target);
                if (!res.ok) return setError(res.error);
                setHandoff(false);
                router.push(`/deals/${res.data!.id}`);
              })
            }
          >
            {pending ? "Creating…" : "Create linked deal"}
          </Button>
        </ModalFooter>
      </Modal>
    </div>
  );
}
