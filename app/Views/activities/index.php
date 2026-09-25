<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $link = fn (array $o) => '/activities' . query_with($o + ['page' => '']); ?>
<?= view('partials/page_header', ['title' => 'Activities', 'subtitle' => 'Tasks, calls and meetings across your contacts, companies and deals.', 'actions' =>
  '<div class="flex rounded-md border border-line bg-white p-0.5 text-[13px]"><a class="rounded px-3 py-1 ' . ($view === 'list' ? 'bg-navy text-white' : 'text-ink-700') . '" href="' . esc($link(['view' => 'list']), 'attr') . '">List</a><a class="rounded px-3 py-1 ' . ($view === 'calendar' ? 'bg-navy text-white' : 'text-ink-700') . '" href="' . esc($link(['view' => 'calendar']), 'attr') . '">Calendar</a></div>' . view('partials/activity_quick', ['linked' => []])]) ?>
<form class="mb-4 flex flex-wrap items-center gap-2" method="get" data-instant-filter>
  <input type="hidden" name="view" value="<?= $view ?>">
  <?php if ($view === 'list'): ?>
    <div class="flex rounded-md border border-line bg-white p-0.5 text-[13px]" data-testid="range-tabs">
      <?php foreach (['today' => 'Today (' . $counts['today'] . ')', 'upcoming' => 'Upcoming', 'overdue' => 'Overdue (' . $counts['overdue'] . ')', 'completed' => 'Completed', 'all' => 'All'] as $k => $label): ?>
        <a class="rounded px-3 py-1 <?= $range === $k ? 'bg-primary text-white' : ($k === 'overdue' && $counts['overdue'] ? 'text-danger' : 'text-ink-700') ?>" href="<?= esc($link(['range' => $k]), 'attr') ?>"><?= $label ?></a>
      <?php endforeach ?>
    </div>
    <input type="hidden" name="range" value="<?= $range ?>">
  <?php else: ?>
    <input type="hidden" name="month" value="<?= $month ?>">
  <?php endif ?>
  <select class="select w-36" name="type"><option value="">All types</option><option value="TASK"<?= selected_if($type === 'TASK') ?>>Tasks</option><option value="CALL"<?= selected_if($type === 'CALL') ?>>Calls</option><option value="EVENT"<?= selected_if($type === 'EVENT') ?>>Meetings</option></select>
  <select class="select w-44" name="assignee"><option value="me"<?= selected_if($assignee === 'me') ?>>Assigned to me</option><option value="all"<?= selected_if($assignee === 'all') ?>>Everyone</option><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"<?= selected_if((string) $assignee === (string) $u['id']) ?>><?= esc($u['name']) ?></option><?php endforeach ?></select>
  <button class="btn btn-secondary" type="submit" data-apply>Apply</button>
</form>
<?php if ($view === 'list'): ?>
  <div class="card card-pad"><?= view('partials/activity_list', ['items' => $rows, 'showLinks' => true, 'emptyText' => $range === 'overdue' ? 'Nothing overdue. Nice.' : 'No activities in this view.']) ?></div>
<?php else: ?>
  <?php $first = new DateTime($monthStart); $prev = (clone $first)->modify('-1 month')->format('Y-m'); $next = (clone $first)->modify('+1 month')->format('Y-m'); $dow = (int) $first->format('N'); $days = (int) $first->format('t'); ?>
  <div class="mb-3 flex items-center gap-2"><a class="btn btn-secondary btn-sm" href="<?= esc($link(['month' => $prev]), 'attr') ?>">‹</a><h2 class="card-title w-40 text-center"><?= $first->format('F Y') ?></h2><a class="btn btn-secondary btn-sm" href="<?= esc($link(['month' => $next]), 'attr') ?>">›</a><a class="btn btn-ghost btn-sm" href="<?= esc($link(['month' => date('Y-m')]), 'attr') ?>">Today</a></div>
  <div class="card overflow-hidden" data-testid="calendar">
    <div class="grid grid-cols-7 border-b border-line-100 bg-surface/60 text-center text-[11px] font-semibold uppercase tracking-wide text-ink-500"><?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d): ?><div class="py-1.5"><?= $d ?></div><?php endforeach ?></div>
    <div class="grid grid-cols-7">
      <?php for ($i = 1; $i < $dow; $i++): ?><div class="min-h-24 border-b border-r border-line-100 bg-surface/30"></div><?php endfor ?>
      <?php for ($day = 1; $day <= $days; $day++): $key = sprintf('%s-%02d', $month, $day); $items = $byDay[$key] ?? []; ?>
        <div class="min-h-24 border-b border-r border-line-100 p-1.5 <?= $key === $today ? 'bg-primary-50/60' : '' ?>" data-day="<?= $key ?>">
          <div class="mb-1 text-xs font-medium <?= $key === $today ? 'text-primary' : 'muted' ?>"><?= $day ?></div>
          <?php foreach (array_slice($items, 0, 4) as $a): ?>
            <a href="/activities?focus=<?= $a['id'] ?>&range=all&assignee=all" class="mb-0.5 block truncate rounded px-1 py-0.5 text-[11px] <?= $a['status'] === 'COMPLETED' ? 'line-through muted bg-surface' : ($a['type'] === 'EVENT' ? 'bg-warning-bg text-warning-fg' : ($a['type'] === 'CALL' ? 'bg-info-bg text-info-fg' : 'bg-surface text-ink-700')) ?>" title="<?= esc($a['title'], 'attr') ?>"><?= $a['all_day'] ? '' : format_time($a['due_at']) . ' ' ?><?= esc($a['title']) ?></a>
          <?php endforeach ?>
          <?php if (count($items) > 4): ?><div class="text-[11px] muted">+<?= count($items) - 4 ?> more</div><?php endif ?>
        </div>
      <?php endfor ?>
    </div>
  </div>
<?php endif ?>
<?= view('partials/activity_dialog', ['linked' => []]) ?>
<?php if (! empty($focus)): ?><div hidden data-focus-activity="<?= (int) $focus ?>"></div><?php endif ?>
<?php if (! empty($p['new'])): ?><div hidden data-auto-open="activity-dialog"></div><?php endif ?>
<?= $this->endSection() ?>
