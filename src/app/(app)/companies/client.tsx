"use client";

import { useState, useTransition } from "react";
import { Plus, Pencil, GitMerge, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Modal, ModalFooter } from "@/components/ui/modal";
import { FormError } from "@/components/ui/form";
import { RecordPicker, type FieldDef } from "@/components/app/fields";
import { CompanyFormModal, type CompanyFormValues } from "@/components/app/company-form";
import { deleteCompany, mergeCompanies, searchCompanies } from "@/lib/actions/companies";

type Common = { users: { id: string; name: string }[]; fieldDefs: FieldDef[]; tagSuggestions: string[]; meId: string };

export function NewCompanyButton(props: Common) {
  const [open, setOpen] = useState(false);
  return (
    <>
      <Button onClick={() => setOpen(true)}>
        <Plus size={16} /> New company
      </Button>
      <CompanyFormModal open={open} onClose={() => setOpen(false)} {...props} />
    </>
  );
}

export function CompanyActions({ company, canEdit, canDelete, ...common }: Common & { company: CompanyFormValues & { id: string; name: string }; canEdit: boolean; canDelete: boolean }) {
  const [editing, setEditing] = useState(false);
  const [merging, setMerging] = useState(false);
  const [source, setSource] = useState<{ id: string; label: string } | null>(null);
  const [error, setError] = useState<string>();
  const [pending, start] = useTransition();

  return (
    <div className="flex items-center gap-2">
      {canEdit && (
        <Button variant="secondary" size="sm" onClick={() => setEditing(true)}>
          <Pencil size={14} /> Edit
        </Button>
      )}
      {canEdit && (
        <Button variant="secondary" size="sm" onClick={() => setMerging(true)}>
          <GitMerge size={14} /> Merge
        </Button>
      )}
      {canDelete && (
        <Button
          variant="ghost"
          size="sm"
          className="text-danger"
          disabled={pending}
          onClick={() => {
            if (confirm(`Delete ${company.name}? Its contacts and deals stay but lose the company link.`))
              start(async () => {
                const res = await deleteCompany(company.id);
                if (!res.ok) alert(res.error);
              });
          }}
        >
          <Trash2 size={14} /> Delete
        </Button>
      )}
      <CompanyFormModal open={editing} onClose={() => setEditing(false)} values={company} {...common} />
      <Modal open={merging} onClose={() => setMerging(false)} title={`Merge into ${company.name}`} size="sm">
        <p className="text-sm text-ink-700">
          Choose the duplicate company to merge <strong>into</strong> {company.name}. Its contacts, deals, notes and files
          move here; then it is deleted.
        </p>
        <div className="mt-4">
          <RecordPicker name="source" placeholder="Search company to merge away…" search={searchCompanies} onChange={setSource} />
        </div>
        <div className="mt-3">
          <FormError message={error} />
        </div>
        <ModalFooter>
          <Button variant="secondary" onClick={() => setMerging(false)}>
            Cancel
          </Button>
          <Button
            variant="danger"
            disabled={!source || source.id === company.id || pending}
            onClick={() =>
              start(async () => {
                const res = await mergeCompanies(company.id, source!.id);
                if (!res.ok) setError(res.error);
                else {
                  setMerging(false);
                  setSource(null);
                }
              })
            }
          >
            {pending ? "Merging…" : "Merge"}
          </Button>
        </ModalFooter>
      </Modal>
    </div>
  );
}
