<div class="mb-5 flex flex-wrap items-start justify-between gap-3">
  <div>
    <h1 class="text-xl font-semibold text-navy"><?= esc($title) ?></h1>
    <?php if (! empty($subtitle)): ?><p class="mt-0.5 text-[13px] text-ink-500"><?= esc($subtitle) ?></p><?php endif ?>
  </div>
  <?php if (! empty($actions)): ?><div class="flex flex-wrap items-center gap-2"><?= $actions ?></div><?php endif ?>
</div>
