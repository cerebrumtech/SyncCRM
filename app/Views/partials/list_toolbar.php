<?php /** $p (GET params), $users, $tags, optional $extra (html) */ ?>
<form class="mb-4 flex flex-wrap items-center gap-2" method="get" data-instant-filter>
  <?php foreach (['sort', 'dir', 'view', 'pipeline'] as $keep): if (! empty($p[$keep])): ?><input type="hidden" name="<?= $keep ?>" value="<?= esc($p[$keep], 'attr') ?>"><?php endif; endforeach ?>
  <div class="relative w-72"><span class="pointer-events-none absolute left-2.5 top-2 text-ink-500"><?= icon('search') ?></span><input class="input pl-8" name="q" placeholder="Search…" value="<?= esc($p['q'] ?? '', 'attr') ?>"></div>
  <select class="select w-44" name="owner"><option value="">All owners</option><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"<?= selected_if(($p['owner'] ?? '') == $u['id']) ?>><?= esc($u['name']) ?></option><?php endforeach ?></select>
  <?php if ($tags): ?><select class="select w-40" name="tag"><option value="">All tags</option><?php foreach ($tags as $t): ?><option value="<?= esc($t, 'attr') ?>"<?= selected_if(($p['tag'] ?? '') === $t) ?>><?= esc($t) ?></option><?php endforeach ?></select><?php endif ?>
  <?= $extra ?? '' ?>
  <button class="btn btn-secondary" type="submit" data-apply>Apply</button>
</form>
