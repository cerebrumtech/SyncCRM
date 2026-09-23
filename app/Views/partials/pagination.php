<?php if (($total ?? 0) > ($perPage ?? 25)): $pages = (int) ceil($total / $perPage); ?>
<div class="mt-3 flex items-center justify-between text-[13px] text-ink-500">
  <span>Showing <?= ($page - 1) * $perPage + 1 ?>–<?= min($page * $perPage, $total) ?> of <?= $total ?></span>
  <div class="flex gap-1">
    <?php if ($page > 1): ?><a class="btn btn-secondary btn-sm" href="<?= esc(query_with(['page' => $page - 1]), 'attr') ?>">Previous</a><?php endif ?>
    <?php if ($page < $pages): ?><a class="btn btn-secondary btn-sm" href="<?= esc(query_with(['page' => $page + 1]), 'attr') ?>">Next</a><?php endif ?>
  </div>
</div>
<?php endif ?>
