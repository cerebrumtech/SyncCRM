<?php /** $entity, $record, $shares, $users, $teams */ ?>
<dialog id="share-dialog" class="modal"><form method="post" action="/shares"><?= csrf_field() ?><input type="hidden" name="entity" value="<?= $entity ?>"><input type="hidden" name="entity_id" value="<?= $record['id'] ?>">
  <h2 class="card-title mb-1">Share this record</h2>
  <p class="mb-3 text-[13px] muted">Shared users and teams can see it in their lists. Tick "can edit" to let them change it.</p>
  <?php if ($shares): ?><ul class="mb-3 divide-y divide-line-100 rounded-md border border-line-100">
    <?php foreach ($shares as $s): ?><li class="flex items-center justify-between px-3 py-1.5 text-[13px]" data-testid="share-row"><span><?= $s['user_name'] ? esc($s['user_name']) : icon('users', 'inline h-3.5 w-3.5') . ' ' . esc($s['team_name']) ?> <?= $s['can_edit'] ? badge('can edit', 'info') : badge('view only') ?></span>
      <button type="submit" formaction="/shares/<?= $s['id'] ?>/delete" class="muted hover:text-danger" title="Remove"><?= icon('x', 'h-3.5 w-3.5') ?></button></li><?php endforeach ?></ul><?php endif ?>
  <div class="grid gap-3 sm:grid-cols-2">
    <div class="field"><label class="label" for="share-user">User</label><select class="select" id="share-user" name="user_id"><option value="">—</option><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"><?= esc($u['name']) ?></option><?php endforeach ?></select></div>
    <div class="field"><label class="label" for="share-team">or Team</label><select class="select" id="share-team" name="team_id"><option value="">—</option><?php foreach ($teams as $t): ?><option value="<?= $t['id'] ?>"><?= esc($t['name']) ?></option><?php endforeach ?></select></div>
  </div>
  <label class="mb-3 flex items-center gap-2 text-[13px]"><input type="checkbox" name="can_edit"> Can edit</label>
  <div class="flex justify-end gap-2"><button type="button" class="btn btn-secondary" data-close>Close</button><button class="btn btn-primary" type="submit">Share</button></div>
</form></dialog>
