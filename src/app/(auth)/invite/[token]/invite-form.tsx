"use client";

import { useActionState } from "react";
import { acceptInvite } from "@/lib/actions/auth";
import { Field, Input } from "@/components/ui/input";
import { FormError, SubmitButton } from "@/components/ui/form";

export function InviteForm({ token }: { token: string }) {
  const [state, action] = useActionState(acceptInvite, undefined);
  return (
    <form action={action} className="space-y-4">
      <input type="hidden" name="token" value={token} />
      <FormError message={state?.error} />
      <Field label="Your name" htmlFor="name" required>
        <Input id="name" name="name" autoComplete="name" required autoFocus />
      </Field>
      <Field label="Password" htmlFor="password" required hint="At least 8 characters">
        <Input id="password" name="password" type="password" autoComplete="new-password" minLength={8} required />
      </Field>
      <SubmitButton className="w-full" size="lg" pendingText="Joining…">
        Join workspace
      </SubmitButton>
    </form>
  );
}
