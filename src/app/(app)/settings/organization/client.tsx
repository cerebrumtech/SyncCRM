"use client";

import { useState, useTransition } from "react";
import { Button } from "@/components/ui/button";
import { Field, Input } from "@/components/ui/input";
import { FormError, FormSuccess } from "@/components/ui/form";
import { updateDedupeRules, updateOrganization } from "@/lib/actions/users";
import { Select } from "@/components/ui/input";
import type { DedupeRules } from "@/lib/dedupe";

const MODES = [
  { value: "warn", label: "Warn — show the match, allow creating anyway" },
  { value: "block", label: "Block — refuse to create a duplicate" },
  { value: "off", label: "Off — don't check" },
];

export function DedupeForm({ rules }: { rules: DedupeRules }) {
  const [msg, setMsg] = useState<{ error?: string; success?: string }>({});
  const [pending, start] = useTransition();
  return (
    <form
      action={(fd) =>
        start(async () => {
          const res = await updateDedupeRules(fd);
          setMsg(res.ok ? { success: "Saved." } : { error: res.error });
        })
      }
      className="space-y-4"
    >
      <FormError message={msg.error} />
      <FormSuccess message={msg.success} />
      <p className="text-sm text-ink-700">What happens when someone creates a record that matches an existing one.</p>
      {(
        [
          ["contactEmail", "Contact with the same email"],
          ["contactPhone", "Contact with the same phone / WhatsApp number"],
          ["companyName", "Company with the same name"],
        ] as const
      ).map(([key, label]) => (
        <Field key={key} label={label} htmlFor={`dd-${key}`}>
          <Select id={`dd-${key}`} name={key} defaultValue={rules[key]}>
            {MODES.map((m) => (
              <option key={m.value} value={m.value}>
                {m.label}
              </option>
            ))}
          </Select>
        </Field>
      ))}
      <Button type="submit" disabled={pending}>
        {pending ? "Saving…" : "Save rules"}
      </Button>
    </form>
  );
}

export function OrgForm({ name, timezone, currency }: { name: string; timezone: string; currency: string }) {
  const [msg, setMsg] = useState<{ error?: string; success?: string }>({});
  const [pending, start] = useTransition();
  return (
    <form
      action={(fd) =>
        start(async () => {
          const res = await updateOrganization(fd);
          setMsg(res.ok ? { success: "Saved." } : { error: res.error });
        })
      }
      className="space-y-4"
    >
      <FormError message={msg.error} />
      <FormSuccess message={msg.success} />
      <Field label="Organisation name" htmlFor="org-name" required>
        <Input id="org-name" name="name" defaultValue={name} required />
      </Field>
      <div className="grid grid-cols-2 gap-3">
        <Field label="Timezone" htmlFor="tz" hint="Fixed for this release">
          <Input id="tz" value={timezone} readOnly disabled />
        </Field>
        <Field label="Currency" htmlFor="cur" hint="Fixed for this release">
          <Input id="cur" value={currency} readOnly disabled />
        </Field>
      </div>
      <Button type="submit" disabled={pending}>
        {pending ? "Saving…" : "Save changes"}
      </Button>
    </form>
  );
}
