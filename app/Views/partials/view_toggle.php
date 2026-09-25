<?php
/**
 * List / Sheet switch.
 *
 * The options are named $toggleViews, not $views: the list screens already have a $views
 * in scope holding the saved views, and a partial inherits the caller's variables. Calling
 * it $views made this silently iterate the saved views instead.
 */
$toggleViews = $toggleViews ?? ['' => 'List', 'sheet' => 'Sheet'];
$current = $current ?? '';
$icons = ['' => 'list', 'sheet' => 'columns'];
?>
<div class="flex rounded-md border border-line bg-white p-0.5">
  <?php foreach ($toggleViews as $key => $label): $on = (string) $current === (string) $key; ?>
    <a class="rounded px-2 py-1 text-xs font-medium <?= $on ? 'bg-navy text-white' : 'text-ink-700' ?>"
       href="<?= esc(query_with(['view' => (string) $key, 'page' => '']), 'attr') ?>" data-testid="view-<?= $key === '' ? 'list' : esc($key, 'attr') ?>">
      <?= icon($icons[$key] ?? 'list', 'inline h-3.5 w-3.5') ?> <?= esc($label) ?>
    </a>
  <?php endforeach ?>
</div>
