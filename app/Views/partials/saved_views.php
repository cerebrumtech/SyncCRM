<?php
/** $entity, $views, $me, $exportHref */
$current = \App\Libraries\Lists::currentFilters();
$hasFilters = (bool) $current;
$base = \App\Libraries\Lists::PATHS[$entity];
$activeId = null;
foreach ($views as $v) { $f = $v['filters'] ?? []; ksort($f); $c = $current; ksort($c); if ($f == $c) { $activeId = $v['id']; break; } }
$isAdmin = \App\Libraries\Permissions::isAdmin($me);
?>
<div class="mb-4 flex flex-wrap items-center gap-2" data-testid="saved-views">
  <a href="<?= $base ?>" class="rounded-full border px-3 py-1 text-xs font-medium <?= ! $hasFilters ? 'border-navy bg-navy text-white' : 'border-line bg-white text-ink-700 hover:border-primary' ?>">All</a>
  <?php foreach ($views as $v): ?>
    <span class="group inline-flex items-center gap-1 rounded-full border pl-3 pr-1 text-xs font-medium <?= $activeId === $v['id'] ? 'border-primary bg-primary text-white' : 'border-line bg-white text-ink-700 hover:border-primary' ?>">
      <a href="<?= $base ?>?<?= http_build_query($v['filters'] ?? []) ?>" class="py-1" title="<?= $v['is_shared'] ? 'Shared by ' . esc($v['owner_name'], 'attr') : 'Private view' ?>"><?= esc($v['name']) ?></a>
      <?php if ($v['owner_id'] === $me['id'] || $isAdmin): ?><form method="post" action="/views/<?= $v['id'] ?>/delete" data-confirm="Delete view &quot;<?= esc($v['name'], 'attr') ?>&quot;?" class="inline"><?= csrf_field() ?><button class="rounded-full p-1 opacity-0 hover:bg-black/10 group-hover:opacity-100" title="Delete view"><?= icon('x', 'h-3 w-3') ?></button></form><?php endif ?>
    </span>
  <?php endforeach ?>
  <?php if ($hasFilters && ! $activeId): ?><button type="button" class="btn btn-ghost btn-sm" data-open="save-view-dialog">Save this view</button><?php endif ?>
  <a href="<?= $exportHref ?><?= $current ? (str_contains($exportHref, '?') ? '&' : '?') . http_build_query($current) : '' ?>" class="btn btn-secondary ml-auto" title="Download the current list as CSV"><?= icon('download') ?> Export CSV</a>
</div>
<dialog id="save-view-dialog" class="modal"><form method="post" action="/views"><?= csrf_field() ?>
  <input type="hidden" name="entity" value="<?= $entity ?>"><input type="hidden" name="filters" value="<?= esc(http_build_query($current), 'attr') ?>">
  <h2 class="card-title mb-3">Save view</h2>
  <div class="field"><label class="label" for="view-name">Name</label><input class="input" id="view-name" name="name" placeholder="e.g. My Pune leads" required></div>
  <p class="hint mb-3">Saves the current search and filters: <?= esc(implode(', ', array_map(fn ($k, $v) => "$k=$v", array_keys($current), $current))) ?></p>
  <label class="mb-3 flex items-center gap-2 text-[13px]"><input type="checkbox" name="is_shared"> Share with the whole team</label>
  <div class="flex justify-end gap-2"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary" type="submit">Save view</button></div>
</form></dialog>
