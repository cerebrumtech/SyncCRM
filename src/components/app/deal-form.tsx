"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Field, Input, Select } from "@/components/ui/input";
import { Modal, ModalFooter } from "@/components/ui/modal";
import { FormError } from "@/components/ui/form";
import { CustomFieldInputs, RecordPicker, TagInput, type FieldDef } from "@/components/app/fields";
import { createDeal, updateDeal } from "@/lib/actions/deals";
import { searchContacts } from "@/lib/actions/contacts";
import { searchCompanies } from "@/lib/actions/companies";
import type { CustomValues } from "@/lib/custom-fields";

export type PipelineOption = { id: string; name: string; isDefault: boolean; stages: { id: string; name: string; kind: "OPEN" | "WON" | "LOST" }[] };

export type DealFormValues = {
  id?: string;
  title?: string;
  pipelineId?: string;
  stageId?: string;
  amount?: number;
  amountIsManual?: boolean;
  expectedCloseDate?: string | null;
  contactId?: string | null;
  contactName?: string | null;
  companyId?: string | null;
  companyName?: string | null;
  ownerId?: string | null;
  tags?: string[];
  customFields?: CustomValues;
};

export function DealFormModal({
  open,
  onClose,
  values,
  pipelines,
  users,
  fieldDefs,
  tagSuggestions,
  meId,
  onCreated,
}: {
  open: boolean;
  onClose: () => void;
  values?: DealFormValues;
  pipelines: PipelineOption[];
  users: { id: string; name: string }[];
  fieldDefs: FieldDef[];
  tagSuggestions: string[];
  meId: string;
  onCreated?: (id: string) => void;
}) {
  const router = useRouter();
  const editing = !!values?.id;
  const initialPipeline = pipelines.find((p) => p.id === values?.pipelineId) ?? pipelines.find((p) => p.isDefault) ?? pipelines[0];
  const [pipelineId, setPipelineId] = useState(initialPipeline?.id ?? "");
  const pipeline = pipelines.find((p) => p.id === pipelineId);
  const [error, setError] = useState<string>();
  const [pending, start] = useTransition();
  const [company, setCompany] = useState<{ id: string; label: string } | null>(
    values?.companyId && values.companyName ? { id: values.companyId, label: values.companyName } : null,
  );

  return (
    <Modal open={open} onClose={onClose} title={editing ? "Edit deal" : "New deal"} size="lg">
      <form
        onSubmit={(e) => {
          e.preventDefault();
          const fd = new FormData(e.currentTarget);
          setError(undefined);
          start(async () => {
            if (editing) {
              const res = await updateDeal(values!.id!, fd);
              if (!res.ok) return setError(res.error);
              onClose();
              router.refresh();
              return;
            }
            const res = await createDeal(fd);
            if (!res.ok) return setError(res.error);
            onClose();
            if (onCreated) onCreated(res.data!.id);
            else router.push(`/deals/${res.data!.id}`);
          });
        }}
        className="space-y-4"
      >
        <FormError message={error} />
        <Field label="Deal title" htmlFor="deal-title" required>
          <Input id="deal-title" name="title" defaultValue={values?.title ?? ""} placeholder="e.g. SyncLMS licence — Acme Patsanstha" required autoFocus />
        </Field>
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Pipeline" htmlFor="deal-pipeline" required>
            <Select id="deal-pipeline" name="pipelineId" value={pipelineId} onChange={(e) => setPipelineId(e.target.value)} disabled={editing}>
              {pipelines.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </Select>
            {editing && <input type="hidden" name="pipelineId" value={pipelineId} />}
          </Field>
          <Field label="Stage" htmlFor="deal-stage" required>
            <Select id="deal-stage" name="stageId" key={pipelineId} defaultValue={values?.stageId ?? pipeline?.stages.find((s) => s.kind === "OPEN")?.id}>
              {pipeline?.stages.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field
            label="Amount (₹)"
            htmlFor="deal-amount"
            hint={values?.amountIsManual === false ? "Calculated from line items" : undefined}
          >
            <Input id="deal-amount" name="amount" type="number" min={0} step="1" defaultValue={values?.amount ?? ""} disabled={values?.amountIsManual === false} />
            {values?.amountIsManual === false && <input type="hidden" name="amount" value={values.amount ?? 0} />}
          </Field>
          <Field label="Expected close date" htmlFor="deal-close">
            <Input id="deal-close" name="expectedCloseDate" type="date" defaultValue={values?.expectedCloseDate ?? ""} />
          </Field>
          <Field label="Contact" htmlFor="contactId">
            <RecordPicker
              name="contactId"
              placeholder="Search contacts…"
              search={searchContacts}
              initial={values?.contactId && values.contactName ? { id: values.contactId, label: values.contactName } : null}
            />
          </Field>
          <Field label="Company" htmlFor="companyId" hint={!company ? "Filled from the contact's company if left blank" : undefined}>
            <RecordPicker name="companyId" placeholder="Search companies…" search={searchCompanies} initial={company} onChange={setCompany} />
          </Field>
          <Field label="Owner" htmlFor="deal-owner">
            <Select id="deal-owner" name="ownerId" defaultValue={values?.ownerId ?? meId}>
              {users.map((u) => (
                <option key={u.id} value={u.id}>
                  {u.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Tags" htmlFor="tags">
            <TagInput defaultValue={values?.tags ?? []} suggestions={tagSuggestions} />
          </Field>
        </div>
        <CustomFieldInputs defs={fieldDefs} values={values?.customFields ?? {}} />
        <ModalFooter>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" disabled={pending}>
            {pending ? "Saving…" : editing ? "Save changes" : "Create deal"}
          </Button>
        </ModalFooter>
      </form>
    </Modal>
  );
}
