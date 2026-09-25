<?php
/**
 * A spreadsheet-style grid. One row per record, many more columns than the list view.
 *
 * $cols    array of ['key' => string, 'label' => string, 'render' => callable(row): string, 'class' => ?string]
 * $rows    the records
 * $entity  used to remember the chosen columns per entity, per browser
 *
 * Sticky positioning is written inline rather than as classes: app.css is a built
 * Tailwind file, and a class that is not in it silently does nothing.
 */
$cols = $cols ?? [];
$rows = $rows ?? [];
$entity = $entity ?? 'ROWS';
$stickyHead = 'position:sticky;top:0;z-index:3;background:#fff;box-shadow:inset 0 -1px 0 var(--color-line,#e5e7eb)';
$stickyCell = 'position:sticky;left:0;z-index:2;background:#fff;box-shadow:inset -1px 0 0 var(--color-line,#e5e7eb)';
$stickyBoth = $stickyHead . ';left:0;z-index:4;box-shadow:inset -1px 0 0 var(--color-line,#e5e7eb),inset 0 -1px 0 var(--color-line,#e5e7eb)';
?>
<div class="mb-2 flex items-center gap-2">
  <div class="relative">
    <button class="btn btn-secondary btn-sm" data-dropdown type="button" data-testid="sheet-columns"><?= icon('columns') ?> Columns</button>
    <div class="dropdown max-h-80 overflow-y-auto" hidden>
      <?php foreach ($cols as $c): ?>
        <label class="flex items-center gap-2 px-3 py-1.5 text-[13px]">
          <input type="checkbox" data-sheet-col="<?= esc($c['key'], 'attr') ?>" checked>
          <span><?= esc($c['label']) ?></span>
        </label>
      <?php endforeach ?>
    </div>
  </div>
  <span class="text-xs muted">Scroll sideways for more. Your column choice is remembered on this device.</span>
</div>
<div class="card overflow-auto" style="max-height:70vh" data-sheet="<?= esc($entity, 'attr') ?>">
  <table class="table" data-testid="sheet-table">
    <thead>
      <tr>
        <?php foreach ($cols as $i => $c): ?>
          <th data-col="<?= esc($c['key'], 'attr') ?>" style="<?= $i === 0 ? $stickyBoth : $stickyHead ?>;white-space:nowrap"><?= esc($c['label']) ?></th>
        <?php endforeach ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <?php foreach ($cols as $i => $c): ?>
            <td data-col="<?= esc($c['key'], 'attr') ?>" class="<?= esc($c['class'] ?? '', 'attr') ?>" style="<?= $i === 0 ? $stickyCell : '' ?>;white-space:nowrap"><?= ($c['render'])($r) ?></td>
          <?php endforeach ?>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>
