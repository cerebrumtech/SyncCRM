<?php
/**
 * A spreadsheet-style grid. One row per record, many more columns than the list view.
 *
 * $cols    array of:
 *            'key'    => string
 *            'label'  => string
 *            'render' => callable(row): string        already-escaped HTML
 *            'class'  => ?string
 *            'edit'   => ?string                      the field name, when the cell is editable
 *            'raw'    => ?callable(row): string       what to put in the editor
 * $rows     the records
 * $entity   CONTACT | COMPANY | DEAL — remembers the chosen columns per entity, per browser
 * $kind     the API segment for saving: contacts | companies | deals
 * $canEdit  ?callable(row): bool — false makes the whole row read-only
 *
 * Cells are edited in place and saved one at a time. What may be written, and what a
 * value is allowed to be, is decided by App\Libraries\SheetEdit on the server; the
 * metadata here only decides what the browser offers, and is never trusted.
 *
 * Sticky positioning is written inline rather than as classes: app.css is a built
 * Tailwind file, and a class that is not in it silently does nothing.
 */
$cols    = $cols ?? [];
$rows    = $rows ?? [];
$entity  = $entity ?? 'ROWS';
$kind    = $kind ?? '';
$canEdit = $canEdit ?? null;
$options = $kind !== '' ? \App\Libraries\SheetEdit::options($entity, (int) ($me['organization_id'] ?? 0)) : [];

$stickyHead = 'position:sticky;top:0;z-index:3;background:#fff;box-shadow:inset 0 -1px 0 var(--color-line,#e5e7eb)';
$stickyCell = 'position:sticky;left:0;z-index:2;background:#fff;box-shadow:inset -1px 0 0 var(--color-line,#e5e7eb)';
$stickyBoth = $stickyHead . ';left:0;z-index:4;box-shadow:inset -1px 0 0 var(--color-line,#e5e7eb),inset 0 -1px 0 var(--color-line,#e5e7eb)';
?>
<div class="mb-2 flex flex-wrap items-center gap-2">
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
  <span class="text-xs muted">
    <?php if ($kind !== ''): ?>
      Click a cell to change it. Enter saves, Esc cancels, Tab moves along.
    <?php else: ?>
      Scroll sideways for more.
    <?php endif ?>
    Your column choice is remembered on this device.
  </span>
  <span class="text-xs" data-sheet-status hidden></span>
</div>

<div class="card overflow-auto" style="max-height:70vh"
     data-sheet="<?= esc($entity, 'attr') ?>"
     <?php if ($kind !== ''): ?>
       data-sheet-kind="<?= esc($kind, 'attr') ?>"
       data-sheet-options="<?= esc(json_encode($options), 'attr') ?>"
     <?php endif ?>>
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
        <?php $editable = $kind !== '' && ($canEdit === null || $canEdit($r)); ?>
        <tr data-id="<?= (int) $r['id'] ?>"<?= $editable ? '' : ' data-readonly' ?>>
          <?php foreach ($cols as $i => $c): ?>
            <?php
              $field = $editable ? ($c['edit'] ?? null) : null;
              $spec  = $field ? \App\Libraries\SheetEdit::spec($entity, $field) : null;
              $rawValue = '';
              if ($spec) {
                  $rawValue = isset($c['raw']) ? (string) ($c['raw'])($r) : (string) ($r[$field] ?? '');
              }
            ?>
            <td data-col="<?= esc($c['key'], 'attr') ?>"
                class="<?= esc($c['class'] ?? '', 'attr') ?>"
                style="<?= $i === 0 ? $stickyCell : '' ?>;white-space:nowrap"
                <?php if ($spec): ?>
                  data-edit="<?= esc($field, 'attr') ?>"
                  data-type="<?= esc($spec['type'], 'attr') ?>"
                  data-value="<?= esc($rawValue, 'attr') ?>"
                  title="<?= esc($spec['label'] . ' — click to edit', 'attr') ?>"
                  <?php if ($spec['type'] === 'select'): ?>data-options="<?= esc($spec['options'], 'attr') ?>"<?php endif ?>
                  <?php if ($spec['type'] === 'lookup'): ?>data-search="<?= esc($spec['search'], 'attr') ?>"<?php endif ?>
                <?php endif ?>><?= ($c['render'])($r) ?></td>
          <?php endforeach ?>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>
