<?php /** $defs, $values (array), $prefix (id prefix) */ $values = $values ?? []; $prefix = $prefix ?? 'cf'; ?>
<?php foreach ($defs as $d): $k = 'cf_' . $d['field_key']; $old = old($k); $v = $old !== null ? $old : ($values[$d['field_key']] ?? null); $id = $prefix . '-' . $d['field_key']; ?>
  <div class="field">
    <?php if ($d['type'] === 'CHECKBOX'): ?>
      <label class="flex items-center gap-2 text-[13px]"><input type="checkbox" name="<?= $k ?>" id="<?= $id ?>"<?= checked_if((bool) $v) ?>> <?= esc($d['label']) ?></label>
    <?php else: ?>
      <label class="label" for="<?= $id ?>"><?= esc($d['label']) ?><?= $d['required'] ? ' *' : '' ?></label>
      <?php if ($d['type'] === 'SELECT'): ?>
        <select class="select" name="<?= $k ?>" id="<?= $id ?>"<?= $d['required'] ? ' required' : '' ?>><option value="">—</option><?php foreach ($d['options'] ?? [] as $o): ?><option value="<?= esc($o, 'attr') ?>"<?= selected_if((string) $v === (string) $o) ?>><?= esc($o) ?></option><?php endforeach ?></select>
      <?php else: ?>
        <input class="input" name="<?= $k ?>" id="<?= $id ?>" type="<?= $d['type'] === 'NUMBER' ? 'number' : ($d['type'] === 'DATE' ? 'date' : 'text') ?>"<?= $d['type'] === 'NUMBER' ? ' step="any"' : '' ?> value="<?= esc((string) ($v ?? ''), 'attr') ?>"<?= $d['required'] ? ' required' : '' ?>>
      <?php endif ?>
    <?php endif ?>
  </div>
<?php endforeach ?>
