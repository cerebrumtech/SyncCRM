"use client";

import { useState, useTransition } from "react";
import { ArrowDown, ArrowUp, Pencil, Plus, Star, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Checkbox, Field, Input, Select } from "@/components/ui/input";
import { Card, CardHeader } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Modal, ModalFooter } from "@/components/ui/modal";
import { FormError } from "@/components/ui/form";
import {
  createPipeline,
  createStage,
  deletePipeline,
  deleteStage,
  renamePipeline,
  reorderStages,
  setDefaultPipeline,
  updateStage,
  type StageInput,
} from "@/lib/actions/pipelines";

type StageRow = StageInput & { id: string; dealCount: number };
type PipelineRow = { id: string; name: string; isDefault: boolean; dealCount: number; stages: StageRow[] };
type RuleField = { key: string; label: string };

export function NewPipelineForm() {
  const [error, setError] = useState<string>();
  const [pending, start] = useTransition();
  return (
    <form
      action={(fd) =>
        start(async () => {
          const res = await createPipeline(fd);
          setError(res.ok ? undefined : res.error);
        })
      }
      className="space-y-3"
    >
      <FormError message={error} />
      <Field label="Pipeline name" htmlFor="pipeline-name" required>
        <Input id="pipeline-name" name="name" placeholder="e.g. Renewals" required />
      </Field>
      <Button type="submit" disabled={pending} className="w-full">
        {pending ? "Creating…" : "Create pipeline"}
      </Button>
    </form>
  );
}

export function PipelineEditor({ pipeline, ruleFields, canDelete }: { pipeline: PipelineRow; ruleFields: RuleField[]; canDelete: boolean }) {
  const [pending, start] = useTransition();
  const [error, setError] = useState<string>();
  const [renaming, setRenaming] = useState(false);
  const [editing, setEditing] = useState<StageRow | "new" | null>(null);
  const [deleting, setDeleting] = useState<StageRow | null>(null);

  function move(idx: number, dir: -1 | 1) {
    const ids = pipeline.stages.map((s) => s.id);
    const j = idx + dir;
    if (j < 0 || j >= ids.length) return;
    [ids[idx], ids[j]] = [ids[j]!, ids[idx]!];
    start(async () => {
      const res = await reorderStages(pipeline.id, ids);
      setError(res.ok ? undefined : res.error);
    });
  }

  return (
    <Card>
      <CardHeader
        title={
          <span className="flex items-center gap-2">
            {renaming ? (
              <form
                action={(fd) =>
                  start(async () => {
                    const res = await renamePipeline(pipeline.id, fd);
                    setError(res.ok ? undefined : res.error);
                    setRenaming(false);
                  })
                }
                className="flex items-center gap-2"
              >
                <Input name="name" defaultValue={pipeline.name} className="h-8 w-56" autoFocus required />
                <Button type="submit" size="sm">
                  Save
                </Button>
                <Button type="button" size="sm" variant="ghost" onClick={() => setRenaming(false)}>
                  Cancel
                </Button>
              </form>
            ) : (
              <>
                {pipeline.name}
                <button type="button" className="text-ink-500 hover:text-ink" onClick={() => setRenaming(true)} title="Rename">
                  <Pencil size={13} />
                </button>
              </>
            )}
            {pipeline.isDefault && <Badge tone="info">Default</Badge>}
            <span className="text-sm font-normal text-ink-500">· {pipeline.dealCount} deals</span>
          </span>
        }
        action={
          <div className="flex items-center gap-1">
            {!pipeline.isDefault && (
              <Button variant="ghost" size="sm" disabled={pending} onClick={() => start(async () => void (await setDefaultPipeline(pipeline.id)))}>
                <Star size={14} /> Make default
              </Button>
            )}
            <Button variant="secondary" size="sm" onClick={() => setEditing("new")}>
              <Plus size={14} /> Add stage
            </Button>
            {canDelete && (
              <Button
                variant="ghost"
                size="sm"
                className="text-danger"
                disabled={pending}
                onClick={() => {
                  if (confirm(`Delete pipeline "${pipeline.name}"?`))
                    start(async () => {
                      const res = await deletePipeline(pipeline.id);
                      setError(res.ok ? undefined : res.error);
                    });
                }}
              >
                <Trash2 size={14} />
              </Button>
            )}
          </div>
        }
      />
      <div className="p-4">
        <FormError message={error} />
        <ol className="space-y-1.5">
          {pipeline.stages.map((s, i) => (
            <li key={s.id} className="flex items-center gap-3 rounded-md border border-line-100 bg-page px-3 py-2">
              <span className="h-3 w-3 shrink-0 rounded-full" style={{ background: s.color }} />
              <span className="w-8 text-xs text-ink-500">{i + 1}.</span>
              <span className="flex-1 text-sm font-medium">
                {s.name}
                {s.kind !== "OPEN" && (
                  <Badge tone={s.kind === "WON" ? "success" : "danger"} className="ml-2">
                    {s.kind}
                  </Badge>
                )}
              </span>
              <span className="w-16 text-xs text-ink-500 tabular">{s.probability}%</span>
              <span className="w-24 text-xs text-ink-500">{s.dealCount} deals</span>
              <span className="hidden max-w-56 truncate text-xs text-ink-500 md:inline" title={s.requiredFields.join(", ")}>
                {s.requiredFields.length ? `Requires: ${s.requiredFields.map((f) => ruleFields.find((r) => r.key === f)?.label ?? f).join(", ")}` : ""}
              </span>
              <div className="flex items-center gap-0.5">
                <button type="button" className="rounded p-1 text-ink-500 hover:bg-white hover:text-ink disabled:opacity-30" disabled={i === 0 || pending} onClick={() => move(i, -1)} title="Move up">
                  <ArrowUp size={14} />
                </button>
                <button type="button" className="rounded p-1 text-ink-500 hover:bg-white hover:text-ink disabled:opacity-30" disabled={i === pipeline.stages.length - 1 || pending} onClick={() => move(i, 1)} title="Move down">
                  <ArrowDown size={14} />
                </button>
                <button type="button" className="rounded p-1 text-ink-500 hover:bg-white hover:text-ink" onClick={() => setEditing(s)} title="Edit stage">
                  <Pencil size={14} />
                </button>
                <button type="button" className="rounded p-1 text-ink-500 hover:bg-white hover:text-danger" onClick={() => setDeleting(s)} title="Delete stage">
                  <Trash2 size={14} />
                </button>
              </div>
            </li>
          ))}
        </ol>
      </div>

      {editing && (
        <StageModal
          key={editing === "new" ? "new" : editing.id}
          pipelineId={pipeline.id}
          stage={editing === "new" ? null : editing}
          ruleFields={ruleFields}
          onClose={() => setEditing(null)}
        />
      )}
      {deleting && (
        <Modal open onClose={() => setDeleting(null)} title={`Delete stage "${deleting.name}"?`} size="sm">
          <DeleteStageForm stage={deleting} others={pipeline.stages.filter((s) => s.id !== deleting.id)} onClose={() => setDeleting(null)} />
        </Modal>
      )}
    </Card>
  );
}

function StageModal({ pipelineId, stage, ruleFields, onClose }: { pipelineId: string; stage: StageRow | null; ruleFields: RuleField[]; onClose: () => void }) {
  const [error, setError] = useState<string>();
  const [pending, start] = useTransition();
  const [kind, setKind] = useState<StageInput["kind"]>(stage?.kind ?? "OPEN");
  const [required, setRequired] = useState<Set<string>>(new Set(stage?.requiredFields ?? []));

  return (
    <Modal open onClose={onClose} title={stage ? "Edit stage" : "Add stage"} size="sm">
      <form
        action={(fd) =>
          start(async () => {
            const input: StageInput = {
              name: String(fd.get("name") ?? ""),
              probability: Number(fd.get("probability") ?? 0),
              color: String(fd.get("color") ?? "#0068FF"),
              kind,
              requiredFields: [...required],
            };
            const res = stage ? await updateStage(stage.id, input) : await createStage(pipelineId, input);
            if (!res.ok) setError(res.error);
            else onClose();
          })
        }
        className="space-y-3"
      >
        <FormError message={error} />
        <Field label="Stage name" htmlFor="stage-name" required>
          <Input id="stage-name" name="name" defaultValue={stage?.name ?? ""} required autoFocus />
        </Field>
        <div className="grid grid-cols-3 gap-3">
          <Field label="Type" htmlFor="stage-kind">
            <Select id="stage-kind" value={kind} onChange={(e) => setKind(e.target.value as StageInput["kind"])}>
              <option value="OPEN">Open</option>
              <option value="WON">Won (closed)</option>
              <option value="LOST">Lost (closed)</option>
            </Select>
          </Field>
          <Field label="Win %" htmlFor="stage-prob">
            <Input id="stage-prob" name="probability" type="number" min={0} max={100} defaultValue={stage?.probability ?? 50} disabled={kind !== "OPEN"} />
          </Field>
          <Field label="Colour" htmlFor="stage-color">
            <Input id="stage-color" name="color" type="color" defaultValue={stage?.color ?? "#0068FF"} className="h-9 p-1" />
          </Field>
        </div>
        <div>
          <p className="mb-1 text-[13px] font-medium text-ink-700">Required to enter this stage</p>
          <div className="grid grid-cols-2 gap-1.5">
            {ruleFields.map((f) => (
              <label key={f.key} className="flex items-center gap-2 text-sm">
                <Checkbox
                  checked={required.has(f.key)}
                  onChange={(e) => {
                    const next = new Set(required);
                    if (e.target.checked) next.add(f.key);
                    else next.delete(f.key);
                    setRequired(next);
                  }}
                />
                {f.label}
              </label>
            ))}
          </div>
        </div>
        <ModalFooter>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" disabled={pending}>
            {pending ? "Saving…" : stage ? "Save stage" : "Add stage"}
          </Button>
        </ModalFooter>
      </form>
    </Modal>
  );
}

function DeleteStageForm({ stage, others, onClose }: { stage: StageRow; others: StageRow[]; onClose: () => void }) {
  const [error, setError] = useState<string>();
  const [pending, start] = useTransition();
  const [target, setTarget] = useState(others[0]?.id ?? "");
  return (
    <div className="space-y-3">
      <FormError message={error} />
      {stage.dealCount > 0 ? (
        <Field label={`Move its ${stage.dealCount} deal(s) to`} htmlFor="move-to">
          <Select id="move-to" value={target} onChange={(e) => setTarget(e.target.value)}>
            {others.map((s) => (
              <option key={s.id} value={s.id}>
                {s.name}
              </option>
            ))}
          </Select>
        </Field>
      ) : (
        <p className="text-sm text-ink-700">This stage has no deals. It will be removed from the pipeline.</p>
      )}
      <ModalFooter>
        <Button variant="secondary" onClick={onClose}>
          Cancel
        </Button>
        <Button
          variant="danger"
          disabled={pending}
          onClick={() =>
            start(async () => {
              const res = await deleteStage(stage.id, stage.dealCount > 0 ? target : null);
              if (!res.ok) setError(res.error);
              else onClose();
            })
          }
        >
          Delete stage
        </Button>
      </ModalFooter>
    </div>
  );
}
