<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $who = $owner ? ($userMap[$owner]['name'] ?? 'the team') : 'the team'; ?>
<?= view('partials/page_header', ['title' => 'Dashboard', 'subtitle' => 'Welcome back, ' . $me['name'] . ". Here's how " . $who . ' is doing ' . strtolower($ranges[$range]) . '.']) ?>
<form class="mb-5 flex flex-wrap items-center gap-2" method="get" data-instant-filter>
  <select class="select w-44" name="pipeline"><?php foreach ($pipelines as $pl): ?><option value="<?= $pl['id'] ?>"<?= selected_if($pipeline && $pl['id'] === $pipeline['id']) ?>><?= esc($pl['name']) ?></option><?php endforeach ?></select>
  <select class="select w-40" name="range"><?php foreach ($ranges as $k => $l): ?><option value="<?= $k ?>"<?= selected_if($range === $k) ?>><?= $l ?></option><?php endforeach ?></select>
  <select class="select w-44" name="owner"><option value="">Everyone</option><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"<?= selected_if($owner === $u['id']) ?>><?= esc($u['name']) ?></option><?php endforeach ?></select>
  <button class="btn btn-secondary" type="submit" data-apply>Apply</button>
</form>
<div class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5" data-testid="stats">
  <a class="stat hover:border-primary/40" href="/deals?<?= $qs(['view' => 'list', 'status' => 'OPEN']) ?>"><div class="stat-label">Open pipeline · <?= esc($pipeline['name'] ?? '') ?></div><div class="stat-value"><?= format_compact_inr($openValue) ?></div><div class="text-xs muted"><?= $openCount ?> open deal<?= $openCount === 1 ? '' : 's' ?></div></a>
  <a class="stat hover:border-primary/40" href="/deals?<?= $qs(['view' => 'list', 'status' => 'WON', 'pipeline' => '']) ?>"><div class="stat-label">Won</div><div class="stat-value text-success-fg"><?= format_compact_inr($won['total']) ?></div><div class="text-xs muted"><?= $won['n'] ?> deal<?= (int) $won['n'] === 1 ? '' : 's' ?> · <?= $ranges[$range] ?></div></a>
  <a class="stat hover:border-primary/40" href="/deals?<?= $qs(['view' => 'list', 'status' => 'LOST', 'pipeline' => '']) ?>"><div class="stat-label">Lost</div><div class="stat-value text-danger-fg"><?= format_compact_inr($lost['total']) ?></div><div class="text-xs muted"><?= $lost['n'] ?> deal<?= (int) $lost['n'] === 1 ? '' : 's' ?><?= $winRate !== null ? ' · ' . $winRate . '% win rate' : '' ?></div></a>
  <a class="stat hover:border-primary/40" href="/activities?range=overdue"><div class="stat-label">My tasks</div><div class="stat-value"><?= $myOpen ?></div><div class="text-xs <?= $myOverdue ? 'font-medium text-danger' : 'muted' ?>"><?= $myOverdue ?> overdue · <?= $myToday ?> due today</div></a>
  <a class="stat hover:border-primary/40" href="/activities?range=completed&type=TASK&assignee=<?= $owner ?? 'all' ?>"><div class="stat-label">Tasks completed</div><div class="stat-value"><?= $taskStatus['COMPLETED'] ?></div><div class="text-xs muted"><?= $taskStatus['OPEN'] ?> still open · <?= $ranges[$range] ?></div></a>
</div>
<div class="grid gap-5 lg:grid-cols-2">
  <div class="card card-pad"><div class="mb-3 flex items-center justify-between"><h2 class="card-title">Pipeline by stage · <?= esc($pipeline['name'] ?? '') ?></h2><?php if ($pipeline): ?><a class="text-xs link" href="/deals?pipeline=<?= $pipeline['id'] ?>">Open board</a><?php endif ?></div><?= view('partials/bar_list', ['rows' => $stageRows, 'empty' => 'No open deals in this pipeline.']) ?></div>
  <div class="card card-pad"><h2 class="card-title mb-3">Won vs lost revenue · last 6 months</h2>
    <?php $maxM = max(1, max(array_map(fn ($m) => max($m['won'], $m['lost']), $months)));
    // With nothing closed yet every bar is a 2% stub, which reads as a broken chart rather
    // than an empty one. Say it in words, as the other widgets on this page do.
    $hasClosed = (bool) array_filter($months, fn ($m) => $m['won'] > 0 || $m['lost'] > 0); ?>
    <?php if (! $hasClosed): ?>
      <p class="muted text-[13px]">No deals won or lost in the last 6 months.</p>
    <?php else: ?>
    <div class="flex h-40 items-end gap-3" data-testid="won-lost">
      <?php foreach ($months as $m): ?>
        <div class="flex flex-1 flex-col items-center gap-1">
          <div class="flex h-32 w-full items-end justify-center gap-1">
            <a class="w-1/2 rounded-t bg-success" style="height:<?= max(2, (int) round($m['won'] / $maxM * 100)) ?>%" title="Won <?= format_inr($m['won']) ?> (<?= $m['wonCount'] ?>)" href="/deals?view=list&status=WON<?= $owner ? '&owner=' . $owner : '' ?>"></a>
            <a class="w-1/2 rounded-t bg-danger/70" style="height:<?= max(2, (int) round($m['lost'] / $maxM * 100)) ?>%" title="Lost <?= format_inr($m['lost']) ?> (<?= $m['lostCount'] ?>)" href="/deals?view=list&status=LOST<?= $owner ? '&owner=' . $owner : '' ?>"></a>
          </div>
          <span class="text-[11px] muted"><?= $m['label'] ?></span>
        </div>
      <?php endforeach ?>
    </div>
    <div class="mt-2 flex gap-4 text-xs muted"><span><span class="mr-1 inline-block h-2 w-2 rounded-sm bg-success"></span>Won</span><span><span class="mr-1 inline-block h-2 w-2 rounded-sm bg-danger/70"></span>Lost</span></div>
    <?php endif ?>
  </div>
  <div class="card card-pad"><h2 class="card-title mb-3">Sales by rep · <?= $ranges[$range] ?></h2><?= view('partials/bar_list', ['rows' => $byRep, 'color' => '#10B981', 'empty' => 'No deals won in this period.']) ?></div>
  <div class="card card-pad"><h2 class="card-title mb-3">Activity volume · <?= $ranges[$range] ?></h2><?= view('partials/bar_list', ['rows' => $activityRows, 'color' => '#04A2FB']) ?></div>
  <div class="card card-pad lg:col-span-2"><div class="mb-3 flex items-center justify-between"><h2 class="card-title">Up next for you</h2><a class="text-xs link" href="/activities">All activities</a></div><?= view('partials/activity_list', ['items' => $upcoming, 'showLinks' => true, 'emptyText' => 'Nothing scheduled. Enjoy the quiet, or create a task.']) ?></div>
</div>
<p class="mt-6 text-xs muted">Amounts in ₹ (INR) · dates in IST.</p>
<?= view('partials/activity_dialog', ['linked' => []]) ?>
<?= $this->endSection() ?>
