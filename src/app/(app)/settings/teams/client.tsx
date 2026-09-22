"use client";

import { useState, useTransition } from "react";
import { Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Checkbox, Field, Input } from "@/components/ui/input";
import { Card, CardHeader } from "@/components/ui/card";
import { Avatar } from "@/components/ui/avatar";
import { FormError } from "@/components/ui/form";
import { createTeam, deleteTeam, setTeamMembers } from "@/lib/actions/users";

export function NewTeamForm() {
  const [error, setError] = useState<string>();
  const [pending, start] = useTransition();
  return (
    <form
      action={(fd) =>
        start(async () => {
          const res = await createTeam(fd);
          setError(res.ok ? undefined : res.error);
        })
      }
      className="space-y-3"
    >
      <FormError message={error} />
      <Field label="Team name" htmlFor="team-name" required>
        <Input id="team-name" name="name" placeholder="e.g. Pune Sales" required />
      </Field>
      <Button type="submit" disabled={pending} className="w-full">
        {pending ? "Creating…" : "Create team"}
      </Button>
    </form>
  );
}

export function TeamCard({
  team,
  users,
}: {
  team: { id: string; name: string; memberIds: string[] };
  users: { id: string; name: string; color: string }[];
}) {
  const [selected, setSelected] = useState<Set<string>>(new Set(team.memberIds));
  const [pending, start] = useTransition();
  const [error, setError] = useState<string>();
  const dirty =
    selected.size !== team.memberIds.length || team.memberIds.some((id) => !selected.has(id));

  return (
    <Card>
      <CardHeader
        title={
          <span>
            {team.name} <span className="text-sm font-normal text-ink-500">· {team.memberIds.length} members</span>
          </span>
        }
        action={
          <Button
            variant="ghost"
            size="sm"
            className="text-danger"
            disabled={pending}
            onClick={() => {
              if (confirm(`Delete team "${team.name}"?`)) start(async () => void (await deleteTeam(team.id)));
            }}
          >
            <Trash2 size={15} /> Delete
          </Button>
        }
      />
      <div className="p-4">
        <FormError message={error} />
        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
          {users.map((u) => (
            <label key={u.id} className="flex cursor-pointer items-center gap-2 rounded-md border border-line-100 px-3 py-2 hover:bg-surface">
              <Checkbox
                checked={selected.has(u.id)}
                onChange={(e) => {
                  const next = new Set(selected);
                  if (e.target.checked) next.add(u.id);
                  else next.delete(u.id);
                  setSelected(next);
                }}
              />
              <Avatar name={u.name} color={u.color} size={24} />
              <span className="text-sm">{u.name}</span>
            </label>
          ))}
        </div>
        {dirty && (
          <div className="mt-3 flex justify-end">
            <Button
              size="sm"
              disabled={pending}
              onClick={() =>
                start(async () => {
                  const res = await setTeamMembers(team.id, [...selected]);
                  setError(res.ok ? undefined : res.error);
                })
              }
            >
              {pending ? "Saving…" : "Save members"}
            </Button>
          </div>
        )}
      </div>
    </Card>
  );
}
