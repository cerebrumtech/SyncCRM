"use client";

import { useState, useTransition } from "react";
import { ArrowDown, ArrowUp, Pencil, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Checkbox, Field, Input, Select } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Modal, ModalFooter } from "@/components/ui/modal";
import { FormError } from "@/components/ui/form";
import { createField, deleteField, moveField, updateField } from "@/lib/actions/fields";

type Def = { id: string; key: string; label: string; type: "TEXT" | "NUMBER" | "DATE" | "SELECT" | "CHECKBOX"; options: string[]; required: boolean };

const TYPE_LABEL = { TEXT: "Text", NUMBER: "Number", DATE: "Date", SELECT: "Dropdown", CHECKBOX: "Checkbox" };

export function NewFieldForm() {
  const [error, setError] = useState<string>();
  const [type, setType] = useState<Def["type"]>("TEXT");
  const [pending, start] = useTransition();
  return (
    <form
      action={(fd) =>
        start(async () => {
          const res = await createField(fd);
          setError(res.ok ? undefined : res.error);
        })
      }
      className="space-y-3"
    >
      <FormError message={error} />
      <Field label="Record type" htmlFor="f-entity">
        <Select id="f-entity" name="entity" defaultValue="CONTACT">
          <option value="CONTACT">Contact</option>
          <option value="COMPANY">Company</option>
          <option value="DEAL">Deal</option>
        </Select>
      </Field>
      <Field label="Label" htmlFor="f-label" required>
        <Input id="f-label" name="label" placeholder="e.g. GST number" required />
      </Field>
      <Field label="Type" htmlFor="f-type">
        <Select id="f-type" name="type" value={type} onChange={(e) => setType(e.target.value as Def["type"])}>
          {Object.entries(TYPE_LABEL).map(([k, v]) => (
            <option key={k} value={k}>
              {v}
            </option>
          ))}
        </Select>
      </Field>
      {type === "SELECT" && (
        <Field label="Options" htmlFor="f-options" hint="Comma separated">
          <Input id="f-options" name="options" placeholder="Bronze, Silver, Gold" />
        </Field>
      )}
      <label className="flex items-center gap-2 text-sm">
        <Checkbox name="required" /> Required
      </label>
      <Button type="submit" disabled={pending} className="w-full">
        {pending ? "Adding…" : "Add field"}
      </Button>
    </form>
  );
}

export function FieldList({ fields }: { fields: Def[] }) {
  const [editing, setEditing] = useState<Def | null>(null);
  const [pending, start] = useTransition();
  if (fields.length === 0) return <p className="text-sm text-ink-500">No custom fields yet.</p>;
  return (
    <>
      <ul className="space-y-1.5">
        {fields.map((f, i) => (
          <li key={f.id} className="flex items-center gap-3 rounded-md border border-line-100 bg-page px-3 py-2 text-sm">
            <span className="flex-1 font-medium">
              {f.label} {f.required && <span className="text-danger">*</span>}
              <span className="ml-2 font-mono text-[11px] text-ink-500">{f.key}</span>
            </span>
            <Badge>{TYPE_LABEL[f.type]}</Badge>
            {f.type === "SELECT" && <span className="hidden max-w-56 truncate text-xs text-ink-500 md:inline">{f.options.join(", ")}</span>}
            <div className="flex items-center gap-0.5">
              <button type="button" className="rounded p-1 text-ink-500 hover:bg-white disabled:opacity-30" disabled={i === 0 || pending} onClick={() => start(async () => void (await moveField(f.id, -1)))} title="Move up">
                <ArrowUp size={14} />
              </button>
              <button type="button" className="rounded p-1 text-ink-500 hover:bg-white disabled:opacity-30" disabled={i === fields.length - 1 || pending} onClick={() => start(async () => void (await moveField(f.id, 1)))} title="Move down">
                <ArrowDown size={14} />
              </button>
              <button type="button" className="rounded p-1 text-ink-500 hover:bg-white" onClick={() => setEditing(f)} title="Edit">
                <Pencil size={14} />
              </button>
              <button
                type="button"
                className="rounded p-1 text-ink-500 hover:bg-white hover:text-danger"
                title="Delete"
                onClick={() => {
                  if (confirm(`Delete field "${f.label}"? Existing values stay stored but will no longer be shown.`)) start(async () => void (await deleteField(f.id)));
                }}
              >
                <Trash2 size={14} />
              </button>
            </div>
          </li>
        ))}
      </ul>
      {editing && <EditFieldModal field={editing} onClose={() => setEditing(null)} />}
    </>
  );
}

function EditFieldModal({ field, onClose }: { field: Def; onClose: () => void }) {
  const [error, setError] = useState<string>();
  const [pending, start] = useTransition();
  return (
    <Modal open onClose={onClose} title={`Edit "${field.label}"`} size="sm">
      <form
        action={(fd) =>
          start(async () => {
            const res = await updateField(field.id, fd);
            if (!res.ok) setError(res.error);
            else onClose();
          })
        }
        className="space-y-3"
      >
        <FormError message={error} />
        <Field label="Label" htmlFor="ef-label" required>
          <Input id="ef-label" name="label" defaultValue={field.label} required autoFocus />
        </Field>
        {field.type === "SELECT" && (
          <Field label="Options" htmlFor="ef-options" hint="Comma separated">
            <Input id="ef-options" name="options" defaultValue={field.options.join(", ")} />
          </Field>
        )}
        <label className="flex items-center gap-2 text-sm">
          <Checkbox name="required" defaultChecked={field.required} /> Required
        </label>
        <ModalFooter>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" disabled={pending}>
            Save
          </Button>
        </ModalFooter>
      </form>
    </Modal>
  );
}
