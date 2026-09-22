"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { CalendarPlus, PhoneCall, Plus } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Checkbox, Field, Input, Select, Textarea } from "@/components/ui/input";
import { Modal, ModalFooter } from "@/components/ui/modal";
import { FormError } from "@/components/ui/form";
import { RecordPicker } from "@/components/app/fields";
import { createActivity, updateActivity } from "@/lib/actions/activities";
import { searchContacts } from "@/lib/actions/contacts";
import { searchCompanies } from "@/lib/actions/companies";
import { searchDeals } from "@/lib/actions/deals";
import { toDateTimeInput } from "@/lib/format";

export type ActivityFormValues = {
  id?: string;
  type?: "TASK" | "CALL" | "EVENT";
  title?: string;
  description?: string | null;
  dueAt?: string | null; // datetime-local
  endAt?: string | null;
  allDay?: boolean;
  location?: string | null;
  recurrence?: "NONE" | "DAILY" | "WEEKLY" | "MONTHLY";
  recurrenceUntil?: string | null;
  reminderMinutes?: number | null;
  attendees?: string[];
  assigneeId?: string | null;
  contact?: { id: string; label: string } | null;
  company?: { id: string; label: string } | null;
  deal?: { id: string; label: string } | null;
  callDirection?: "INBOUND" | "OUTBOUND" | null;
  callDurationMin?: number | null;
  callOutcome?: string | null;
  status?: "OPEN" | "COMPLETED" | "CANCELLED";
};

const TYPE_LABEL = { TASK: "Task", CALL: "Call", EVENT: "Meeting / Event" };

export function ActivityFormModal({
  open,
  onClose,
  values,
  users,
  meId,
  mode = "edit",
}: {
  open: boolean;
  onClose: () => void;
  values?: ActivityFormValues;
  users: { id: string; name: string }[];
  meId: string;
  mode?: "edit" | "log-call";
}) {
  const router = useRouter();
  const editing = !!values?.id;
  const [type, setType] = useState<"TASK" | "CALL" | "EVENT">(mode === "log-call" ? "CALL" : values?.type ?? "TASK");
  const [allDay, setAllDay] = useState(values?.allDay ?? false);
  const [recurrence, setRecurrence] = useState(values?.recurrence ?? "NONE");
  const [followUp, setFollowUp] = useState(false);
  const [error, setError] = useState<string>();
  const [pending, start] = useTransition();
  const [nowMs] = useState(() => Date.now());
  const defaultDue = values?.dueAt ?? (mode === "log-call" ? toDateTimeInput(new Date(nowMs)) : "");

  return (
    <Modal open={open} onClose={onClose} title={editing ? `Edit ${TYPE_LABEL[type].toLowerCase()}` : mode === "log-call" ? "Log a call" : "New activity"} size="lg">
      <form
        onSubmit={(e) => {
          e.preventDefault();
          const fd = new FormData(e.currentTarget);
          setError(undefined);
          start(async () => {
            const res = editing ? await updateActivity(values!.id!, fd) : await createActivity(fd);
            if (!res.ok) return setError(res.error);
            onClose();
            router.refresh();
          });
        }}
        className="space-y-4"
      >
        <FormError message={error} />
        <div className="grid gap-3 sm:grid-cols-[160px_1fr]">
          <Field label="Type" htmlFor="a-type">
            <Select id="a-type" name="type" value={type} onChange={(e) => setType(e.target.value as typeof type)} disabled={mode === "log-call"}>
              <option value="TASK">Task</option>
              <option value="CALL">Call</option>
              <option value="EVENT">Meeting / Event</option>
            </Select>
            {mode === "log-call" && <input type="hidden" name="type" value="CALL" />}
          </Field>
          <Field label="Title" htmlFor="a-title" required>
            <Input id="a-title" name="title" defaultValue={values?.title ?? ""} placeholder={type === "CALL" ? "e.g. Discovery call with Priya" : type === "EVENT" ? "e.g. Demo at Acme office" : "e.g. Send proposal"} required autoFocus />
          </Field>
        </div>

        {type === "CALL" && (
          <div className="grid gap-3 rounded-md border border-line-100 bg-page p-3 sm:grid-cols-3">
            <Field label="Direction" htmlFor="a-dir">
              <Select id="a-dir" name="callDirection" defaultValue={values?.callDirection ?? "OUTBOUND"}>
                <option value="OUTBOUND">Outbound</option>
                <option value="INBOUND">Inbound</option>
              </Select>
            </Field>
            <Field label="Duration (min)" htmlFor="a-dur">
              <Input id="a-dur" name="callDurationMin" type="number" min={0} step="1" defaultValue={values?.callDurationMin ?? ""} />
            </Field>
            <Field label="Outcome" htmlFor="a-outcome">
              <Select id="a-outcome" name="callOutcome" defaultValue={values?.callOutcome ?? ""}>
                <option value="">—</option>
                {["Connected", "Interested", "Not interested", "No answer", "Busy — call back", "Wrong number", "Left voicemail"].map((o) => (
                  <option key={o} value={o}>
                    {o}
                  </option>
                ))}
              </Select>
            </Field>
            {mode === "log-call" && (
              <>
                <input type="hidden" name="logged" value="on" />
                <label className="flex items-center gap-2 text-sm sm:col-span-3">
                  <Checkbox checked={followUp} onChange={(e) => setFollowUp(e.target.checked)} /> Create a follow-up task
                </label>
                {followUp && (
                  <>
                    <Field label="Follow-up task" htmlFor="a-fu" className="sm:col-span-2">
                      <Input id="a-fu" name="followUpTitle" placeholder="e.g. Send pricing" />
                    </Field>
                    <Field label="Due" htmlFor="a-fu-due">
                      <Input id="a-fu-due" name="followUpDueAt" type="datetime-local" defaultValue={toDateTimeInput(new Date(nowMs + 86_400_000))} />
                    </Field>
                  </>
                )}
              </>
            )}
          </div>
        )}

        <div className="grid gap-3 sm:grid-cols-2">
          <Field label={type === "EVENT" ? "Starts" : type === "CALL" ? "When" : "Due"} htmlFor="a-due" required={type === "EVENT"}>
            <Input id="a-due" name="dueAt" type={allDay ? "date" : "datetime-local"} defaultValue={allDay ? defaultDue.slice(0, 10) : defaultDue} required={type === "EVENT"} />
          </Field>
          {type === "EVENT" ? (
            <Field label="Ends" htmlFor="a-end">
              <Input id="a-end" name="endAt" type="datetime-local" defaultValue={values?.endAt ?? ""} disabled={allDay} />
            </Field>
          ) : (
            <Field label="Reminder" htmlFor="a-rem">
              <Select id="a-rem" name="reminderMinutes" defaultValue={values?.reminderMinutes ?? ""}>
                <option value="">No reminder</option>
                <option value="0">At the time</option>
                <option value="15">15 minutes before</option>
                <option value="60">1 hour before</option>
                <option value="1440">1 day before</option>
              </Select>
            </Field>
          )}
          {type === "EVENT" && (
            <>
              <label className="flex items-center gap-2 text-sm">
                <Checkbox name="allDay" checked={allDay} onChange={(e) => setAllDay(e.target.checked)} /> All day
              </label>
              <Field label="Reminder" htmlFor="a-rem2">
                <Select id="a-rem2" name="reminderMinutes" defaultValue={values?.reminderMinutes ?? "15"}>
                  <option value="">No reminder</option>
                  <option value="0">At the time</option>
                  <option value="15">15 minutes before</option>
                  <option value="60">1 hour before</option>
                  <option value="1440">1 day before</option>
                </Select>
              </Field>
              <Field label="Location" htmlFor="a-loc">
                <Input id="a-loc" name="location" defaultValue={values?.location ?? ""} placeholder="Office, client site, phone…" />
              </Field>
              <Field label="Attendees (emails)" htmlFor="a-att" hint="Comma separated">
                <Input id="a-att" name="attendees" defaultValue={values?.attendees?.join(", ") ?? ""} placeholder="priya@acme.in, rahul@acme.in" />
              </Field>
            </>
          )}
          <Field label="Repeat" htmlFor="a-rec">
            <Select id="a-rec" name="recurrence" value={recurrence} onChange={(e) => setRecurrence(e.target.value as typeof recurrence)}>
              <option value="NONE">Does not repeat</option>
              <option value="DAILY">Daily</option>
              <option value="WEEKLY">Weekly</option>
              <option value="MONTHLY">Monthly</option>
            </Select>
          </Field>
          {recurrence !== "NONE" && (
            <Field label="Repeat until" htmlFor="a-until">
              <Input id="a-until" name="recurrenceUntil" type="date" defaultValue={values?.recurrenceUntil ?? ""} />
            </Field>
          )}
          <Field label="Assigned to" htmlFor="a-assignee">
            <Select id="a-assignee" name="assigneeId" defaultValue={values?.assigneeId ?? meId}>
              {users.map((u) => (
                <option key={u.id} value={u.id}>
                  {u.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Contact" htmlFor="contactId">
            <RecordPicker name="contactId" placeholder="Search contacts…" search={searchContacts} initial={values?.contact ?? null} />
          </Field>
          <Field label="Company" htmlFor="companyId">
            <RecordPicker name="companyId" placeholder="Search companies…" search={searchCompanies} initial={values?.company ?? null} />
          </Field>
          <Field label="Deal" htmlFor="dealId">
            <RecordPicker name="dealId" placeholder="Search deals…" search={searchDeals} initial={values?.deal ?? null} />
          </Field>
        </div>
        <Field label="Notes" htmlFor="a-desc">
          <Textarea id="a-desc" name="description" defaultValue={values?.description ?? ""} />
        </Field>
        {editing && (
          <label className="flex items-center gap-2 text-sm">
            <Checkbox name="markCompleted" defaultChecked={values?.status === "COMPLETED"} /> Completed
          </label>
        )}
        <ModalFooter>
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" disabled={pending}>
            {pending ? "Saving…" : editing ? "Save changes" : mode === "log-call" ? "Log call" : "Create"}
          </Button>
        </ModalFooter>
      </form>
    </Modal>
  );
}

// "Add activity" + "Log call" buttons for record pages and the activities list.
export function ActivityQuickActions({ users, meId, linked, compact }: { users: { id: string; name: string }[]; meId: string; linked?: Pick<ActivityFormValues, "contact" | "company" | "deal">; compact?: boolean }) {
  const [open, setOpen] = useState<"new" | "call" | null>(null);
  return (
    <>
      <Button variant={compact ? "secondary" : "primary"} size="sm" onClick={() => setOpen("new")}>
        {compact ? <CalendarPlus size={14} /> : <Plus size={16} />} {compact ? "Activity" : "New activity"}
      </Button>
      <Button variant="secondary" size="sm" onClick={() => setOpen("call")}>
        <PhoneCall size={14} /> Log call
      </Button>
      {open && <ActivityFormModal open onClose={() => setOpen(null)} users={users} meId={meId} mode={open === "call" ? "log-call" : "edit"} values={{ ...linked }} />}
    </>
  );
}
