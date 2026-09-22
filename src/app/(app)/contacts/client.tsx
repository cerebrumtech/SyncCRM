"use client";

import { useState, useTransition } from "react";
import { Plus, Pencil, GitMerge, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Modal, ModalFooter } from "@/components/ui/modal";
import { FormError } from "@/components/ui/form";
import { RecordPicker, type FieldDef } from "@/components/app/fields";
import { ContactFormModal, type ContactFormValues } from "@/components/app/contact-form";
import { deleteContact, mergeContacts, searchContacts } from "@/lib/actions/contacts";

type Common = { users: { id: string; name: string }[]; fieldDefs: FieldDef[]; tagSuggestions: string[]; meId: string };

export function NewContactButton(props: Common & { defaultCompany?: { id: string; label: string } | null; label?: string }) {
  const [open, setOpen] = useState(false);
  return (
    <>
      <Button onClick={() => setOpen(true)}>
        <Plus size={16} /> {props.label ?? "New contact"}
      </Button>
      <ContactFormModal open={open} onClose={() => setOpen(false)} {...props} />
    </>
  );
}

export function ContactActions({ contact, canEdit, canDelete, ...common }: Common & { contact: ContactFormValues & { id: string; name: string }; canEdit: boolean; canDelete: boolean }) {
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
            if (confirm(`Delete ${contact.name}? Their deals and activities will lose this link.`))
              start(async () => {
                const res = await deleteContact(contact.id);
                if (!res.ok) alert(res.error);
              });
          }}
        >
          <Trash2 size={14} /> Delete
        </Button>
      )}
      <ContactFormModal open={editing} onClose={() => setEditing(false)} values={contact} {...common} />
      <Modal open={merging} onClose={() => setMerging(false)} title={`Merge into ${contact.name}`} size="sm">
        <p className="text-sm text-ink-700">
          Choose the duplicate contact to merge <strong>into</strong> {contact.name}. Its deals, activities, notes and files
          move here; empty fields are filled from it; then it is deleted.
        </p>
        <div className="mt-4">
          <RecordPicker name="source" placeholder="Search contact to merge away…" search={searchContacts} onChange={setSource} />
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
            disabled={!source || source.id === contact.id || pending}
            onClick={() =>
              start(async () => {
                const res = await mergeContacts(contact.id, source!.id);
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
