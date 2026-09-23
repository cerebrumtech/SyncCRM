<?php /** $items (activities with assignee_name), $showLinks, $emptyText */ ?>
<?php if (! $items): ?><p class="muted text-[13px]"><?= esc($emptyText ?? 'No activities.') ?></p><?php else: ?>
<ul class="divide-y divide-line-100">
<?php foreach ($items as $a): $overdue = $a['status'] === 'OPEN' && $a['due_at'] && strtotime($a['due_at']) < time(); ?>
  <li class="flex items-start gap-3 py-2.5" data-testid="activity" data-status="<?= $a['status'] ?>" id="activity-<?= $a['id'] ?>">
    <form method="post" action="/activities/<?= $a['id'] ?>/status" class="mt-0.5"><?= csrf_field() ?><input type="hidden" name="status" value="<?= $a['status'] === 'COMPLETED' ? 'OPEN' : 'COMPLETED' ?>">
      <button class="flex h-4 w-4 items-center justify-center rounded border <?= $a['status'] === 'COMPLETED' ? 'border-success bg-success text-white' : 'border-line hover:border-primary' ?>" title="<?= $a['status'] === 'COMPLETED' ? 'Mark open' : 'Mark complete' ?>"><?= $a['status'] === 'COMPLETED' ? icon('check', 'h-3 w-3') : '' ?></button></form>
    <div class="min-w-0 flex-1">
      <div class="flex flex-wrap items-center gap-2">
        <?= badge(activity_type_label($a['type']), $a['type'] === 'CALL' ? 'info' : ($a['type'] === 'EVENT' ? 'warning' : 'neutral')) ?>
        <button type="button" class="text-[13px] font-medium hover:text-primary <?= $a['status'] === 'COMPLETED' ? 'line-through muted' : '' ?>" data-open="activity-dialog" data-action="/activities/<?= $a['id'] ?>" data-fill="<?= esc(json_encode(['type' => $a['type'], 'title' => $a['title'], 'description' => $a['description'], 'due_at' => $a['all_day'] ? date_input($a['due_at']) : datetime_input($a['due_at']), 'end_at' => datetime_input($a['end_at']), 'all_day' => (bool) $a['all_day'], 'location' => $a['location'], 'recurrence' => $a['recurrence'], 'recurrence_until' => date_input($a['recurrence_until']), 'reminder_minutes' => $a['reminder_minutes'], 'attendees' => implode(', ', $a['attendees'] ? (is_array($a['attendees']) ? $a['attendees'] : json_decode($a['attendees'], true)) : []), 'assignee_id' => $a['assignee_id'], 'call_direction' => $a['call_direction'], 'call_duration_min' => $a['call_duration_sec'] ? round($a['call_duration_sec'] / 60) : '', 'call_outcome' => $a['call_outcome'], 'mark_completed' => $a['status'] === 'COMPLETED', 'contact_id' => $a['contact_id'], 'company_id' => $a['company_id'], 'deal_id' => $a['deal_id'], '_title' => 'Edit activity']), 'attr') ?>"><?= esc($a['title']) ?></button>
        <?php if ($a['recurrence'] !== 'NONE'): ?><span class="muted" title="Repeats <?= strtolower($a['recurrence']) ?>"><?= icon('repeat', 'h-3.5 w-3.5') ?></span><?php endif ?>
        <?php if ($a['status'] !== 'OPEN'): ?><?= activity_status_badge($a['status']) ?><?php endif ?>
      </div>
      <div class="mt-0.5 flex flex-wrap gap-x-3 text-xs muted">
        <?php if ($a['due_at']): ?><span class="<?= $overdue ? 'text-danger font-medium' : '' ?>"><?= $a['all_day'] ? format_date($a['due_at']) : format_datetime($a['due_at']) ?><?= $overdue ? ' · overdue' : '' ?></span><?php endif ?>
        <?php if ($a['assignee_name']): ?><span><?= esc($a['assignee_name']) ?></span><?php endif ?>
        <?php if ($a['type'] === 'CALL' && $a['call_outcome']): ?><span><?= esc($a['call_outcome']) ?></span><?php endif ?>
        <?php if (($showLinks ?? true)): ?>
          <?php if (! empty($a['contact_name'])): ?><a class="hover:text-primary" href="/contacts/<?= $a['contact_id'] ?>"><?= esc($a['contact_name']) ?></a><?php endif ?>
          <?php if (! empty($a['company_name'])): ?><a class="hover:text-primary" href="/companies/<?= $a['company_id'] ?>"><?= esc($a['company_name']) ?></a><?php endif ?>
          <?php if (! empty($a['deal_title'])): ?><a class="hover:text-primary" href="/deals/<?= $a['deal_id'] ?>"><?= esc($a['deal_title']) ?></a><?php endif ?>
        <?php endif ?>
      </div>
    </div>
    <form method="post" action="/activities/<?= $a['id'] ?>/delete" data-confirm="Delete this activity?"><?= csrf_field() ?><button class="muted hover:text-danger" title="Delete"><?= icon('trash', 'h-3.5 w-3.5') ?></button></form>
  </li>
<?php endforeach ?>
</ul>
<?php endif ?>
