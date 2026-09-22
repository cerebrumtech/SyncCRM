"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { AlertTriangle } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Field, Input, Select, Textarea } from "@/components/ui/input";
import { Modal, ModalFooter } from "@/components/ui/modal";
import { FormError } from "@/components/ui/form";
import { CustomFieldInputs, TagInput, type FieldDef } from "@/components/app/fields";
import { createCompany, updateCompany, type CompanyDuplicate } from "@/lib/actions/companies";
import type { CustomValues } from "@/lib/custom-fields";

export type CompanyFormValues = {
  id?: string;
  name?: string;
  industry?: string | null;
  website?: string | null;
  phone?: string | null;
  email?: string | null;
  addressLine?: string | null;
  city?: string | null;
  state?: string | null;
  postalCode?: string | null;
  country?: string | null;
  description?: string | null;
  ownerId?: string | null;
  tags?: string[];
  customFields?: CustomValues;
};

export const INDUSTRIES = [
  "Co-operative Credit Society",
  "NBFC",
  "Microfinance",
  "Bank",
  "Fintech",
  "Housing Finance",
  "Insurance",
  "Other",
];

export function CompanyFormModal({
  open,
  onClose,
  values,
  users,
  fieldDefs,
  tagSuggestions,
  meId,
}: {
  open: boolean;
  onClose: () => void;
  values?: CompanyFormValues;
  users: { id: string; name: string }[];
  fieldDefs: FieldDef[];
  tagSuggestions: string[];
  meId: string;
}) {
  const router = useRouter();
  const [error, setError] = useState<string>();
  const [dups, setDups] = useState<CompanyDuplicate[]>([]);
  const [pending, start] = useTransition();
  const editing = !!values?.id;

  function submit(fd: FormData, force = false) {
    if (force) fd.set("force", "true");
    setError(undefined);
    start(async () => {
      if (editing) {
        const res = await updateCompany(values!.id!, fd);
        if (!res.ok) return setError(res.error);
        onClose();
        router.refresh();
        return;
      }
      const res = await createCompany(fd);
      if (!res.ok) return setError(res.error);
      if (res.data?.duplicates.length) return setDups(res.data.duplicates);
      onClose();
      router.push(`/companies/${res.data!.id}`);
    });
  }

  return (
    <Modal open={open} onClose={onClose} title={editing ? "Edit company" : "New company"} size="lg">
      <form
        id="company-form"
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
              <AlertTriangle size={16} /> A company with this name already exists
            </p>
            <ul className="mt-2 space-y-1">
              {dups.map((d) => (
                <li key={d.id}>
                  <Link href={`/companies/${d.id}`} className="font-medium underline" target="_blank">
                    {d.name}
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        )}
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Company name" htmlFor="name" required className="sm:col-span-2">
            <Input id="name" name="name" defaultValue={values?.name ?? ""} required autoFocus />
          </Field>
          <Field label="Industry" htmlFor="industry">
            <Select id="industry" name="industry" defaultValue={values?.industry ?? ""}>
              <option value="">—</option>
              {INDUSTRIES.map((i) => (
                <option key={i} value={i}>
                  {i}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Website" htmlFor="website">
            <Input id="website" name="website" placeholder="https://" defaultValue={values?.website ?? ""} />
          </Field>
          <Field label="Phone" htmlFor="c-phone">
            <Input id="c-phone" name="phone" type="tel" defaultValue={values?.phone ?? ""} />
          </Field>
          <Field label="Email" htmlFor="c-email">
            <Input id="c-email" name="email" type="email" defaultValue={values?.email ?? ""} />
          </Field>
          <Field label="Address" htmlFor="addressLine" className="sm:col-span-2">
            <Input id="addressLine" name="addressLine" defaultValue={values?.addressLine ?? ""} />
          </Field>
          <Field label="City" htmlFor="city">
            <Input id="city" name="city" defaultValue={values?.city ?? ""} />
          </Field>
          <Field label="State" htmlFor="state">
            <Input id="state" name="state" defaultValue={values?.state ?? ""} />
          </Field>
          <Field label="PIN code" htmlFor="postalCode">
            <Input id="postalCode" name="postalCode" defaultValue={values?.postalCode ?? ""} />
          </Field>
          <Field label="Country" htmlFor="country">
            <Input id="country" name="country" defaultValue={values?.country ?? "India"} />
          </Field>
          <Field label="Owner" htmlFor="c-ownerId">
            <Select id="c-ownerId" name="ownerId" defaultValue={values?.ownerId ?? meId}>
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
          <Field label="Description" htmlFor="description" className="sm:col-span-2">
            <Textarea id="description" name="description" defaultValue={values?.description ?? ""} />
          </Field>
        </div>
        <CustomFieldInputs defs={fieldDefs} values={values?.customFields ?? {}} />
        <ModalFooter>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          {dups.length > 0 ? (
            <Button
              variant="danger"
              disabled={pending}
              onClick={() => submit(new FormData(document.getElementById("company-form") as HTMLFormElement), true)}
            >
              Create anyway
            </Button>
          ) : (
            <Button type="submit" disabled={pending}>
              {pending ? "Saving…" : editing ? "Save changes" : "Create company"}
            </Button>
          )}
        </ModalFooter>
      </form>
    </Modal>
  );
}
