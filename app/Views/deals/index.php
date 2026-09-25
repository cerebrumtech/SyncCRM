<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $isBoard = $view === 'board'; $isSheet = $view === 'sheet'; $qs = fn (array $o) => query_with($o); ?>
<?= view('partials/page_header', ['title' => 'Deals', 'subtitle' => $pipeline ? $pipeline['name'] . ' pipeline · ' . $total . ' deal' . ($total === 1 ? '' : 's') : 'No pipeline yet', 'actions' => '<button class="btn btn-primary" data-open="deal-dialog">' . icon('plus') . 'New deal</button>']) ?>
<?= view('partials/saved_views', ['entity' => 'DEAL', 'views' => $views, 'exportHref' => '/export/deals']) ?>
<?php
$extra = '<select class="select w-44" name="pipeline" data-submit-on-change>';
foreach ($pipelines as $pl) { $extra .= '<option value="' . $pl['id'] . '"' . selected_if($pipeline && $pl['id'] === $pipeline['id']) . '>' . esc($pl['name']) . '</option>'; }
$extra .= '</select>';
if (! $isBoard) {
    $extra .= '<select class="select w-32" name="status"><option value="">Any status</option>';
    foreach (['OPEN' => 'Open', 'WON' => 'Won', 'LOST' => 'Lost'] as $k => $l) { $extra .= '<option value="' . $k . '"' . selected_if(($p['status'] ?? '') === $k) . '>' . $l . '</option>'; }
    $extra .= '</select><select class="select w-40" name="stage"><option value="">Any stage</option>';
    foreach ($pipeline['stages'] ?? [] as $s) { $extra .= '<option value="' . $s['id'] . '"' . selected_if(($p['stage'] ?? '') == $s['id']) . '>' . esc($s['name']) . '</option>'; }
    $extra .= '</select>';
}
$extra .= '<div class="ml-auto">' . view('partials/view_toggle', ['toggleViews' => ['' => 'Board', 'list' => 'List', 'sheet' => 'Sheet'], 'current' => $isBoard ? '' : $view]) . '</div>';
?>
<?= view('partials/list_toolbar', ['p' => $p, 'users' => $users, 'tags' => $tags, 'extra' => $extra]) ?>

<?php if (! $pipeline): ?>
  <div class="empty">No pipelines yet. <a class="link" href="/settings/pipelines">Create one in Settings</a>.</div>
<?php elseif ($isBoard): ?>
  <div class="scroll-thin flex gap-3 overflow-x-auto pb-3" data-kanban data-testid="kanban">
    <?php foreach ($columns as $col): $s = $col['stage']; ?>
      <div class="kanban-col" data-testid="kanban-col" data-stage-name="<?= esc($s['name'], 'attr') ?>">
        <div class="flex items-center justify-between px-3 pt-3 pb-2">
          <div class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full" style="background:<?= esc($s['color'], 'attr') ?>"></span><span class="text-[13px] font-semibold text-navy"><?= esc($s['name']) ?></span><span class="badge badge-neutral"><?= count($col['deals']) ?></span></div>
          <span class="text-xs muted tabular"><?= format_compact_inr($col['total']) ?></span>
        </div>
        <div class="min-h-24 flex-1 px-2 pb-2" data-column="<?= $s['id'] ?>">
          <?php foreach ($col['deals'] as $d): $overdue = $d['status'] === 'OPEN' && $d['expected_close_date'] && strtotime($d['expected_close_date']) < strtotime('today'); ?>
            <div class="kanban-card" data-deal="<?= $d['id'] ?>" data-testid="kanban-card">
              <a href="/deals/<?= $d['id'] ?>" class="block text-[13px] font-medium text-navy hover:text-primary"><?= esc($d['title']) ?></a>
              <div class="mt-1 text-xs muted"><?= esc($d['company_name'] ?? trim(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? '')) ?: '—') ?></div>
              <div class="mt-2 flex items-center justify-between">
                <span class="text-[13px] font-semibold tabular"><?= format_inr($d['amount']) ?></span>
                <span class="flex items-center gap-1.5"><?php if ($d['expected_close_date']): ?><span class="text-[11px] <?= $overdue ? 'text-danger font-medium' : 'muted' ?>"><?= format_date($d['expected_close_date']) ?></span><?php endif ?><?= $d['owner_name'] ? avatar($d['owner_name'], $d['owner_color'], 20) : '' ?></span>
              </div>
              <?php if ($d['status'] === 'LOST' && $d['lost_reason']): ?><div class="mt-1 text-[11px] text-danger"><?= esc($d['lost_reason']) ?></div><?php endif ?>
            </div>
          <?php endforeach ?>
        </div>
      </div>
    <?php endforeach ?>
  </div>
<?php elseif ($isSheet): ?>
  <?php if (! $rows): ?><div class="empty">No deals match.</div><?php else: ?>
<?php
$dash = fn ($v) => $v !== null && $v !== '' ? esc($v) : '<span class="muted">—</span>';
$cols = [
  ['key' => 'title',    'label' => 'Deal',      'render' => fn ($d) => '<a href="/deals/' . $d['id'] . '" class="font-medium text-primary hover:underline">' . esc($d['title']) . '</a>'],
  ['key' => 'company',  'label' => 'Company',   'edit' => 'company_id', 'render' => fn ($d) => $dash($d['company_name'] ?? null)],
  ['key' => 'contact',  'label' => 'Contact',   'edit' => 'contact_id', 'render' => fn ($d) => $dash(trim(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? '')) ?: null)],
  ['key' => 'stage',    'label' => 'Stage',     'edit' => 'stage_id', 'render' => fn ($d) => esc($d['stage_name'])],
  ['key' => 'pipeline', 'label' => 'Pipeline',  'render' => fn ($d) => esc($d['pipeline_name'])],
  ['key' => 'status',   'label' => 'Status',    'render' => fn ($d) => deal_status_badge($d['status'])],
  ['key' => 'amount',   'label' => 'Amount',    'edit' => 'amount', 'class' => 'text-right tabular', 'render' => fn ($d) => format_inr($d['amount'])],
  ['key' => 'proposal', 'label' => 'Proposal',  'edit' => 'proposal_amount', 'class' => 'text-right tabular', 'render' => fn ($d) => isset($d['proposal_amount']) && $d['proposal_amount'] !== null ? format_inr($d['proposal_amount']) : '<span class="muted">—</span>'],
  ['key' => 'received', 'label' => 'Received',  'edit' => 'amount_received', 'class' => 'text-right tabular', 'render' => fn ($d) => isset($d['amount_received']) && $d['amount_received'] !== null ? format_inr($d['amount_received']) : '<span class="muted">—</span>'],
  ['key' => 'pending',  'label' => 'Pending',   'edit' => 'amount_pending', 'class' => 'text-right tabular', 'render' => fn ($d) => isset($d['amount_pending']) && $d['amount_pending'] !== null ? format_inr($d['amount_pending']) : '<span class="muted">—</span>'],
  ['key' => 'prob',     'label' => 'Probability','class' => 'text-right tabular', 'render' => fn ($d) => (int) $d['probability'] . '%'],
  ['key' => 'close',    'label' => 'Expected close', 'edit' => 'expected_close_date', 'render' => fn ($d) => $d['expected_close_date'] ? format_date($d['expected_close_date']) : '<span class="muted">—</span>'],
  ['key' => 'closed',   'label' => 'Closed',    'render' => fn ($d) => $d['closed_at'] ? format_date($d['closed_at']) : '<span class="muted">—</span>'],
  ['key' => 'source',   'label' => 'Lead source','edit' => 'lead_source', 'render' => fn ($d) => $dash($d['lead_source'] ?? null)],
  ['key' => 'lost',     'label' => 'Lost reason','render' => fn ($d) => $dash($d['lost_reason'] ?? null)],
  ['key' => 'owner',    'label' => 'Owner',     'edit' => 'owner_id', 'render' => fn ($d) => $d['owner_name'] ? esc($d['owner_name']) : '<span class="muted">Unassigned</span>'],
  ['key' => 'updated',  'label' => 'Updated',   'class' => 'text-xs muted', 'render' => fn ($d) => relative_time($d['updated_at'])],
];
foreach ($defs as $df) {
    $k = $df['field_key'];
    $cols[] = ['key' => 'cf_' . $k, 'label' => $df['label'], 'render' => function ($d) use ($k, $dash) {
        $cf = is_array($d['custom_fields'] ?? null) ? $d['custom_fields'] : json_decode((string) ($d['custom_fields'] ?? ''), true);
        $v = is_array($cf) ? ($cf[$k] ?? null) : null;
        return $dash(is_bool($v) ? ($v ? 'Yes' : 'No') : $v);
    }];
}
?>
  <?= view('partials/sheet', ['cols' => $cols, 'rows' => $rows, 'entity' => 'DEAL', 'kind' => 'deals', 'me' => $me,
    'canEdit' => fn ($r) => \App\Libraries\Permissions::canEditRecord($me, 'DEAL', $r)]) ?>
  <?= view('partials/pagination', ['total' => $total, 'page' => $page, 'perPage' => $perPage]) ?>
  <?php endif ?>
<?php else: ?>
  <?php if (! $rows): ?><div class="empty">No deals match.</div><?php else: ?>
  <div class="card overflow-x-auto"><table class="table" data-testid="deals-table">
    <thead><tr><th><?= sort_link('Deal', 'title') ?></th><th><?= sort_link('Stage', 'stage') ?></th><th class="text-right"><?= sort_link('Amount', 'amount') ?></th><th>Status</th><th>Contact / company</th><th>Owner</th><th><?= sort_link('Close', 'close') ?></th><th><?= sort_link('Updated', 'updated') ?></th></tr></thead>
    <tbody><?php foreach ($rows as $d): ?>
      <tr><td><a class="font-medium text-primary hover:underline" href="/deals/<?= $d['id'] ?>"><?= esc($d['title']) ?></a><?= $d['tags'] ? '<div class="mt-0.5">' . tag_badges(json_decode($d['tags'], true)) . '</div>' : '' ?></td>
        <td><span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full" style="background:<?= esc($d['stage_color'], 'attr') ?>"></span><?= esc($d['stage_name']) ?></span></td>
        <td class="text-right tabular font-medium"><?= format_inr($d['amount']) ?></td><td><?= deal_status_badge($d['status']) ?><?= $d['status'] === 'LOST' && $d['lost_reason'] ? '<div class="text-[11px] muted">' . esc($d['lost_reason']) . '</div>' : '' ?></td>
        <td class="text-[13px]"><?= $d['contact_id'] ? '<a class="hover:text-primary" href="/contacts/' . $d['contact_id'] . '">' . esc(trim($d['first_name'] . ' ' . $d['last_name'])) . '</a>' : '' ?><?= $d['company_id'] ? '<div class="text-xs muted">' . esc($d['company_name']) . '</div>' : '' ?></td>
        <td><?= $d['owner_name'] ? '<span class="inline-flex items-center gap-1.5">' . avatar($d['owner_name'], $d['owner_color'], 22) . '<span class="text-xs">' . esc($d['owner_name']) . '</span></span>' : '<span class="text-xs muted">Unassigned</span>' ?></td>
        <td class="text-xs muted"><?= format_date($d['expected_close_date']) ?></td><td class="text-xs muted"><?= relative_time($d['updated_at']) ?></td></tr>
    <?php endforeach ?></tbody></table></div>
  <?= view('partials/pagination', ['total' => $total, 'page' => $page, 'perPage' => $perPage]) ?>
  <?php endif ?>
<?php endif ?>

<dialog id="deal-dialog" class="modal modal-lg"><form method="post" action="/deals" data-keep-enabled><?= csrf_field() ?>
  <h2 class="card-title mb-3">New deal</h2>
  <?= view('deals/_form', ['deal' => null, 'contact' => null, 'company' => null]) ?>
  <div class="mt-2 flex justify-end gap-2"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary" type="submit">Create deal</button></div>
</form></dialog>
<?= view('deals/_lost_dialog') ?>
<?php if (! empty($p['new'])): ?><div hidden data-auto-open="deal-dialog"></div><?php endif ?>
<?= $this->endSection() ?>
