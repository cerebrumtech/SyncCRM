<?php /** $linked: contact_id/contact_label/company_id/company_label/deal_id/deal_label */ $linked = $linked ?? []; ?>
<div class="flex items-center gap-1" data-testid="quick-actions">
  <button type="button" class="btn btn-secondary btn-sm" data-open="activity-dialog" data-fill="<?= esc(json_encode(['type' => 'TASK', '_title' => 'New task', 'due_at' => date('Y-m-d\TH:i', strtotime('tomorrow 10:00'))] + $linked), 'attr') ?>" data-action="/activities">+ Task</button>
  <button type="button" class="btn btn-secondary btn-sm" data-open="activity-dialog" data-fill="<?= esc(json_encode(['type' => 'CALL', '_title' => 'Log a call', 'due_at' => date('Y-m-d\TH:i'), 'logged' => true, 'call_direction' => 'OUTBOUND'] + $linked), 'attr') ?>" data-action="/activities">Log call</button>
  <button type="button" class="btn btn-secondary btn-sm" data-open="activity-dialog" data-fill="<?= esc(json_encode(['type' => 'EVENT', '_title' => 'Schedule a meeting', 'due_at' => date('Y-m-d\TH:i', strtotime('tomorrow 11:00')), 'end_at' => date('Y-m-d\TH:i', strtotime('tomorrow 12:00'))] + $linked), 'attr') ?>" data-action="/activities">Meeting</button>
</div>
