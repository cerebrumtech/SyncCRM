"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { AlertTriangle } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Field, Input, Select } from "@/components/ui/input";
import { Modal, ModalFooter } from "@/components/ui/modal";
import { FormError } from "@/components/ui/form";
import { CustomFieldInputs, RecordPicker, TagInput, type FieldDef } from "@/components/app/fields";
import { createContact, updateContact, type DuplicateHit } from "@/lib/actions/contacts";
import { searchCompanies } from "@/lib/actions/companies";
import type { CustomValues } from "@/lib/custom-fields";

export type ContactFormValues = {
  id?: string;
  firstName?: string;
  lastName?: string | null;
  email?: string | null;
  phone?: string | null;
  whatsappNumber?: string | null;
  jobTitle?: string | null;
  companyId?: string | null;
  companyName?: string | null;
  ownerId?: string | null;
  tags?: string[];
  customFields?: CustomValues;
};

export function ContactFormModal({
  open,
  onClose,
  values,
  users,
  fieldDefs,
  tagSuggestions,
  meId,
  defaultCompany,
}: {
  open: boolean;
  onClose: () => void;
  values?: ContactFormValues;
  users: { id: string; name: string }[];
  fieldDefs: FieldDef[];
  tagSuggestions: string[];
  meId: string;
  defaultCompany?: { id: string; label: string } | null;
}) {
  const router = useRouter();
  const [error, setError] = useState<string>();
  const [dups, setDups] = useState<DuplicateHit[]>([]);
  const [pending, start] = useTransition();
  const editing = !!values?.id;

  function submit(fd: FormData, force = false) {
    if (force) fd.set("force", "true");
    setError(undefined);
    start(async () => {
      if (editing) {
        const res = await updateContact(values!.id!, fd);
        if (!res.ok) return setError(res.error);
        onClose();
        router.refresh();
        return;
      }
      const res = await createContact(fd);
      if (!res.ok) return setError(res.error);
      if (res.data?.duplicates.length) return setDups(res.data.duplicates);
      onClose();
      router.push(`/contacts/${res.data!.id}`);
    });
  }

  return (
    <Modal open={open} onClose={onClose} title={editing ? "Edit contact" : "New contact"} size="lg">
      <form
        id="contact-form"
        onSubmit={(e) => {
          e.preventDefault();
          submit(new FormData(e.currentTarget));
        }}
        className="space-y-4"
        onChange={() => dups.length && setDups([])}
      >
        <FormError message={error} />
        {dups.length > 0 && (
          <div className="rounded-md border border-warning/40 bg-warning-bg p-3 text-sm text-warning-fg">
            <p className="flex items-center gap-2 font-semibold">
              <AlertTriangle size={16} /> Possible duplicate{dups.length > 1 ? "s" : ""} found
            </p>
            <ul className="mt-2 space-y-1">
              {dups.map((d) => (
                <li key={d.id}>
                  <Link href={`/contacts/${d.id}`} className="font-medium underline" target="_blank">
                    {d.name}
                  </Link>{" "}
                  <span className="text-xs">
                    — {d.reason} {d.email && `· ${d.email}`} {d.phone && `· ${d.phone}`}
                  </span>
                </li>
              ))}
            </ul>
            <p className="mt-2 text-xs">Open the existing contact, or create this one anyway.</p>
          </div>
        )}
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="First name" htmlFor="firstName" required>
            <Input id="firstName" name="firstName" defaultValue={values?.firstName ?? ""} required autoFocus />
          </Field>
          <Field label="Last name" htmlFor="lastName">
            <Input id="lastName" name="lastName" defaultValue={values?.lastName ?? ""} />
          </Field>
          <Field label="Email" htmlFor="email">
            <Input id="email" name="email" type="email" defaultValue={values?.email ?? ""} />
          </Field>
          <Field label="Phone" htmlFor="phone">
            <Input id="phone" name="phone" type="tel" placeholder="+91 98765 43210" defaultValue={values?.phone ?? ""} />
          </Field>
          <Field label="WhatsApp number" htmlFor="whatsappNumber" hint="Leave blank if same as phone">
            <Input id="whatsappNumber" name="whatsappNumber" type="tel" defaultValue={values?.whatsappNumber ?? ""} />
          </Field>
          <Field label="Job title" htmlFor="jobTitle">
            <Input id="jobTitle" name="jobTitle" defaultValue={values?.jobTitle ?? ""} />
          </Field>
          <Field label="Company" htmlFor="companyId">
            <RecordPicker
              name="companyId"
              placeholder="Search companies…"
              search={searchCompanies}
              initial={
                values?.companyId && values.companyName
                  ? { id: values.companyId, label: values.companyName }
                  : defaultCompany ?? null
              }
            />
          </Field>
          <Field label="Owner" htmlFor="ownerId">
            <Select id="ownerId" name="ownerId" defaultValue={values?.ownerId ?? meId}>
              {users.map((u) => (
                <option key={u.id} value={u.id}>
                  {u.name}
                </option>
              ))}
            </Select>
          </Field>
        </div>
        <Field label="Tags" htmlFor="tags">
          <TagInput defaultValue={values?.tags ?? []} suggestions={tagSuggestions} />
        </Field>
        <CustomFieldInputs defs={fieldDefs} values={values?.customFields ?? {}} />
        <ModalFooter>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          {dups.length > 0 ? (
            <Button
              variant="danger"
              disabled={pending}
              onClick={() => {
                const form = document.getElementById("contact-form") as HTMLFormElement;
                submit(new FormData(form), true);
              }}
            >
              Create anyway
            </Button>
          ) : (
            <Button type="submit" disabled={pending}>
              {pending ? "Saving…" : editing ? "Save changes" : "Create contact"}
            </Button>
          )}
        </ModalFooter>
      </form>
    </Modal>
  );
}
