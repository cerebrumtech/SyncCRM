"use client";

import { useActionState } from "react";
import { setupOrganization } from "@/lib/actions/auth";
import { Field, Input } from "@/components/ui/input";
import { FormError, SubmitButton } from "@/components/ui/form";

export function SetupForm() {
  const [state, action] = useActionState(setupOrganization, undefined);
  return (
    <form action={action} className="space-y-4">
      <FormError message={state?.error} />
      <Field label="Organisation name" htmlFor="orgName" required>
        <Input id="orgName" name="orgName" defaultValue="SyncWorks Technologies Pvt. Ltd." required autoFocus />
      </Field>
      <Field label="Your name" htmlFor="name" required>
        <Input id="name" name="name" autoComplete="name" required />
      </Field>
      <Field label="Work email" htmlFor="email" required>
        <Input id="email" name="email" type="email" autoComplete="email" required />
      </Field>
      <Field label="Password" htmlFor="password" required hint="At least 8 characters">
        <Input id="password" name="password" type="password" autoComplete="new-password" minLength={8} required />
      </Field>
      <SubmitButton className="w-full" size="lg" pendingText="Creating workspace…">
        Create workspace
      </SubmitButton>
    </form>
  );
}
