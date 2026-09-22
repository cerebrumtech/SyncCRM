"use client";

import { useState, useTransition } from "react";
import { UserPlus, Copy, Check } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Field, Input, Select } from "@/components/ui/input";
import { Modal, ModalFooter } from "@/components/ui/modal";
import { FormError, FormSuccess } from "@/components/ui/form";
import { deactivateUser, inviteUser, reactivateUser, revokeInvite, updateUserRole } from "@/lib/actions/users";

export function InviteButton({ canInviteOwner }: { canInviteOwner: boolean }) {
  const [open, setOpen] = useState(false);
  const [error, setError] = useState<string>();
  const [link, setLink] = useState<string>();
  const [pending, start] = useTransition();

  function submit(formData: FormData) {
    setError(undefined);
    start(async () => {
      const res = await inviteUser(formData);
      if (!res.ok) setError(res.error);
      else setLink(res.data?.link);
    });
  }

  function close() {
    setOpen(false);
    setLink(undefined);
    setError(undefined);
  }

  return (
    <>
      <Button size="sm" onClick={() => setOpen(true)}>
        <UserPlus size={15} /> Invite user
      </Button>
      <Modal open={open} onClose={close} title="Invite a teammate" size="sm">
        {link ? (
          <div className="space-y-3">
            <FormSuccess message="Invite created. Share this link with your teammate — it expires in 7 days." />
            <CopyField value={link} />
            <ModalFooter>
              <Button variant="secondary" onClick={close}>
                Done
              </Button>
            </ModalFooter>
          </div>
        ) : (
          <form action={submit} className="space-y-4">
            <FormError message={error} />
            <Field label="Work email" htmlFor="inv-email" required>
              <Input id="inv-email" name="email" type="email" required autoFocus />
            </Field>
            <Field label="Role" htmlFor="inv-role">
              <Select id="inv-role" name="role" defaultValue="MEMBER">
                <option value="MEMBER">Member — works own records</option>
                <option value="ADMIN">Admin — manages users and settings</option>
                {canInviteOwner && <option value="OWNER">Owner — full control</option>}
              </Select>
            </Field>
            <ModalFooter>
              <Button variant="secondary" onClick={close}>
                Cancel
              </Button>
              <Button type="submit" disabled={pending}>
                {pending ? "Creating…" : "Create invite link"}
              </Button>
            </ModalFooter>
          </form>
        )}
      </Modal>
    </>
  );
}

export function CopyField({ value }: { value: string }) {
  const [copied, setCopied] = useState(false);
  return (
    <div className="flex gap-2">
      <Input readOnly value={value} onFocus={(e) => e.currentTarget.select()} />
      <Button
        variant="secondary"
        onClick={async () => {
          await navigator.clipboard.writeText(value);
          setCopied(true);
          setTimeout(() => setCopied(false), 1500);
        }}
      >
        {copied ? <Check size={15} /> : <Copy size={15} />}
      </Button>
    </div>
  );
}

export function InviteRowActions({ inviteId, link }: { inviteId: string; link: string }) {
  const [pending, start] = useTransition();
  const [copied, setCopied] = useState(false);
  return (
    <div className="flex justify-end gap-1">
      <Button
        variant="ghost"
        size="sm"
        onClick={async () => {
          await navigator.clipboard.writeText(link);
          setCopied(true);
          setTimeout(() => setCopied(false), 1500);
        }}
      >
        {copied ? "Copied" : "Copy link"}
      </Button>
      <Button
        variant="ghost"
        size="sm"
        className="text-danger"
        disabled={pending}
        onClick={() => start(async () => void (await revokeInvite(inviteId)))}
      >
        Revoke
      </Button>
    </div>
  );
}

type UserRow = { id: string; name: string; email: string; role: "OWNER" | "ADMIN" | "MEMBER"; isActive: boolean };

export function UserRowActions({
  user,
  isSelf,
  actorIsOwner,
  reassignOptions,
}: {
  user: UserRow;
  isSelf: boolean;
  actorIsOwner: boolean;
  reassignOptions: { id: string; name: string }[];
}) {
  const [pending, start] = useTransition();
  const [error, setError] = useState<string>();
  const [deactivating, setDeactivating] = useState(false);
  const [reassignTo, setReassignTo] = useState<string>(reassignOptions[0]?.id ?? "");
  const roleLocked = user.role === "OWNER" && !actorIsOwner;

  function changeRole(role: string) {
    setError(undefined);
    start(async () => {
      const res = await updateUserRole(user.id, role);
      if (!res.ok) setError(res.error);
    });
  }

  return (
    <div className="flex flex-col items-end gap-1">
      <div className="flex items-center gap-2">
        <Select
          className="h-8 w-32 text-[13px]"
          value={user.role}
          disabled={pending || roleLocked}
          onChange={(e) => changeRole(e.target.value)}
        >
          <option value="MEMBER">Member</option>
          <option value="ADMIN">Admin</option>
          <option value="OWNER" disabled={!actorIsOwner}>
            Owner
          </option>
        </Select>
        {!isSelf && user.isActive && !roleLocked && (
          <Button variant="ghost" size="sm" className="text-danger" onClick={() => setDeactivating(true)}>
            Deactivate
          </Button>
        )}
        {!isSelf && !user.isActive && (
          <Button
            variant="ghost"
            size="sm"
            disabled={pending}
            onClick={() =>
              start(async () => {
                const res = await reactivateUser(user.id);
                if (!res.ok) setError(res.error);
              })
            }
          >
            Reactivate
          </Button>
        )}
      </div>
      {error && <p className="text-xs text-danger">{error}</p>}

      <Modal open={deactivating} onClose={() => setDeactivating(false)} title={`Deactivate ${user.name}?`} size="sm">
        <p className="text-sm text-ink-700">
          {user.name} will no longer be able to sign in. Their open contacts, companies, deals and tasks can be
          reassigned now.
        </p>
        <div className="mt-4">
          <Field label="Reassign open records to" htmlFor={`reassign-${user.id}`}>
            <Select id={`reassign-${user.id}`} value={reassignTo} onChange={(e) => setReassignTo(e.target.value)}>
              <option value="">Leave unassigned</option>
              {reassignOptions.map((o) => (
                <option key={o.id} value={o.id}>
                  {o.name}
                </option>
              ))}
            </Select>
          </Field>
        </div>
        <ModalFooter>
          <Button variant="secondary" onClick={() => setDeactivating(false)}>
            Cancel
          </Button>
          <Button
            variant="danger"
            disabled={pending}
            onClick={() =>
              start(async () => {
                const res = await deactivateUser(user.id, reassignTo || null);
                if (!res.ok) setError(res.error);
                setDeactivating(false);
              })
            }
          >
            Deactivate
          </Button>
        </ModalFooter>
      </Modal>
    </div>
  );
}
