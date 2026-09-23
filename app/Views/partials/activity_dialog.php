<?php /** $users, $me, optional $linked */ $linked = $linked ?? []; $outcomes = \App\Libraries\Activities::OUTCOMES; ?>
<dialog id="activity-dialog" class="modal modal-lg"><form method="post" action="/activities" data-activity-form data-keep-enabled><?= csrf_field() ?>
  <h2 class="card-title mb-3" data-dialog-title>New activity</h2>
  <input type="hidden" name="logged" value="">
  <div class="grid gap-3 sm:grid-cols-2">
    <div class="field"><label class="label" for="a-type">Type</label><select class="select" id="a-type" name="type" data-activity-type><option value="TASK">Task</option><option value="CALL">Call</option><option value="EVENT">Meeting / Event</option></select></div>
    <div class="field"><label class="label" for="a-assignee">Assigned to</label><select class="select" id="a-assignee" name="assignee_id"><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"<?= selected_if($u['id'] === $me['id']) ?>><?= esc($u['name']) ?></option><?php endforeach ?></select></div>
    <div class="field sm:col-span-2"><label class="label" for="a-title">Title *</label><input class="input" id="a-title" name="title" value="<?= old_or('title') ?>" required autofocus placeholder="e.g. Send proposal"></div>
    <div data-type-only="CALL" class="contents">
      <div class="field"><label class="label" for="a-dir">Direction</label><select class="select" id="a-dir" name="call_direction"><option value="OUTBOUND">Outbound</option><option value="INBOUND">Inbound</option></select></div>
      <div class="field"><label class="label" for="a-dur">Duration (min)</label><input class="input" id="a-dur" name="call_duration_min" type="number" min="0" step="1"></div>
      <div class="field sm:col-span-2"><label class="label" for="a-outcome">Outcome</label><select class="select" id="a-outcome" name="call_outcome"><option value="">—</option><?php foreach ($outcomes as $o): ?><option value="<?= esc($o, 'attr') ?>"><?= esc($o) ?></option><?php endforeach ?></select></div>
    </div>
    <div class="field"><label class="label" for="a-due" data-due-label>Due</label><input class="input" id="a-due" name="due_at" type="datetime-local"></div>
    <div data-type-only="EVENT" class="contents">
      <div class="field"><label class="label" for="a-end">Ends</label><input class="input" id="a-end" name="end_at" type="datetime-local"></div>
      <div class="field"><label class="label" for="a-loc">Location</label><input class="input" id="a-loc" name="location" placeholder="Office, client site, Google Meet…"></div>
      <div class="field"><label class="label" for="a-att">Attendees (emails, comma separated)</label><input class="input" id="a-att" name="attendees" placeholder="priya@acme.in, rahul@acme.in"></div>
    </div>
    <div class="field"><label class="label" for="a-rem">Reminder</label><select class="select" id="a-rem" name="reminder_minutes"><option value="">No reminder</option><option value="0">At the time</option><option value="15">15 minutes before</option><option value="60">1 hour before</option><option value="1440">1 day before</option></select></div>
    <div class="field"><label class="label" for="a-rec">Repeats</label><select class="select" id="a-rec" name="recurrence"><option value="NONE">Does not repeat</option><option value="DAILY">Daily</option><option value="WEEKLY">Weekly</option><option value="MONTHLY">Monthly</option></select></div>
    <div class="field"><label class="label" for="a-until">Repeat until</label><input class="input" id="a-until" name="recurrence_until" type="date"></div>
    <div class="field flex items-end gap-4 pb-1"><label class="flex items-center gap-2 text-[13px]"><input type="checkbox" name="all_day" data-all-day> All day</label><label class="flex items-center gap-2 text-[13px]"><input type="checkbox" name="mark_completed"> Completed</label></div>
    <div class="field sm:col-span-2"><label class="label" for="a-desc">Notes</label><textarea class="textarea" id="a-desc" name="description" rows="2"></textarea></div>
    <div class="field"><label class="label">Contact</label><div data-picker="/api/search/contacts" data-name="contact_id" data-value="<?= esc($linked['contact_id'] ?? '', 'attr') ?>" data-label="<?= esc($linked['contact_label'] ?? '', 'attr') ?>" data-placeholder="Search contacts…"></div></div>
    <div class="field"><label class="label">Company</label><div data-picker="/api/search/companies" data-name="company_id" data-value="<?= esc($linked['company_id'] ?? '', 'attr') ?>" data-label="<?= esc($linked['company_label'] ?? '', 'attr') ?>" data-placeholder="Search companies…"></div></div>
    <div class="field sm:col-span-2"><label class="label">Deal</label><div data-picker="/api/search/deals" data-name="deal_id" data-value="<?= esc($linked['deal_id'] ?? '', 'attr') ?>" data-label="<?= esc($linked['deal_label'] ?? '', 'attr') ?>" data-placeholder="Search deals…"></div></div>
    <div data-logged-only class="contents" hidden>
      <div class="field sm:col-span-2 border-t border-line-100 pt-3"><label class="label" for="a-fu">Follow-up task (optional)</label><input class="input" id="a-fu" name="follow_up_title" placeholder="e.g. Send pricing"></div>
      <div class="field"><label class="label" for="a-fu-due">Follow-up due</label><input class="input" id="a-fu-due" name="follow_up_due_at" type="datetime-local" value="<?= date('Y-m-d\TH:i', strtotime('+1 day')) ?>"></div>
    </div>
  </div>
  <div class="mt-2 flex justify-end gap-2"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary" type="submit" data-submit-label>Save</button></div>
</form></dialog>
