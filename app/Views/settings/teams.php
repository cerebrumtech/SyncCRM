<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Settings', 'subtitle' => 'Teams group users so records can be shared with everyone in them', 'actions' => '<button class="btn btn-primary" data-open="team-dialog">' . icon('plus') . 'New team</button>']) ?>
<?= view('settings/_nav') ?>
<?php if (! $teams): ?><div class="empty">No teams yet. Create one to share records with a group of users.</div><?php endif ?>
<div class="grid gap-4 md:grid-cols-2">
<?php foreach ($teams as $t): $ids = $members[$t['id']] ?? []; ?>
  <div class="card card-pad" data-testid="team-card">
    <div class="mb-2 flex items-center justify-between"><h2 class="card-title"><?= esc($t['name']) ?></h2>
      <form method="post" action="/settings/teams/<?= $t['id'] ?>/delete" data-confirm="Delete team <?= esc($t['name'], 'attr') ?>?"><?= csrf_field() ?><button class="btn btn-ghost btn-sm"><?= icon('trash') ?></button></form></div>
    <form method="post" action="/settings/teams/<?= $t['id'] ?>/members"><?= csrf_field() ?>
      <div class="mb-3 grid gap-1 sm:grid-cols-2">
        <?php foreach ($users as $u): ?><label class="flex items-center gap-2 rounded px-1 py-0.5 text-[13px] hover:bg-surface"><input type="checkbox" name="user_ids[]" value="<?= $u['id'] ?>"<?= checked_if(in_array($u['id'], $ids, true)) ?>> <?= avatar($u['name'], $u['color'], 20) ?> <?= esc($u['name']) ?></label><?php endforeach ?>
      </div>
      <button class="btn btn-secondary btn-sm" type="submit">Save members</button> <span class="muted text-xs"><?= count($ids) ?> member<?= count($ids) === 1 ? '' : 's' ?></span>
    </form>
  </div>
<?php endforeach ?>
</div>
<dialog id="team-dialog" class="modal"><form method="post" action="/settings/teams"><?= csrf_field() ?>
  <h2 class="card-title mb-3">New team</h2>
  <div class="field"><label class="label" for="team-name">Team name</label><input class="input" id="team-name" name="name" value="<?= old_or('name') ?>" required></div>
  <div class="flex justify-end gap-2"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary" type="submit">Create team</button></div>
</form></dialog>
<?= $this->endSection() ?>
