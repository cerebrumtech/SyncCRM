<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Settings', 'subtitle' => 'Custom fields appear on the record forms, detail pages, CSV import/export and can be required by pipeline stages.', 'actions' => '<button class="btn btn-primary" data-open="field-dialog">' . icon('plus') . 'New field</button>']) ?>
<?= view('settings/_nav') ?>
<div class="grid gap-4 lg:grid-cols-3">
<?php foreach ($entities as $e => $label): $rows = $defs[$e]; ?>
  <div class="card" data-testid="fields-card" data-entity="<?= $e ?>">
    <div class="border-b border-line-100 px-4 py-2.5"><h2 class="card-title"><?= $label ?></h2></div>
    <?php if (! $rows): ?><p class="muted px-4 py-6 text-[13px]">No custom fields yet.</p><?php endif ?>
    <ul class="divide-y divide-line-100">
    <?php foreach ($rows as $i => $d): ?>
      <li class="flex items-center justify-between gap-2 px-4 py-2 text-[13px]" data-testid="field-row">
        <div><span class="font-medium"><?= esc($d['label']) ?></span><?= $d['required'] ? ' ' . badge('required', 'warning') : '' ?><div class="text-xs muted"><?= strtolower($d['type']) ?> · key <code><?= esc($d['field_key']) ?></code><?= $d['options'] ? ' · ' . esc(implode(', ', $d['options'])) : '' ?></div></div>
        <div class="flex shrink-0 items-center">
          <form method="post" action="/settings/fields/<?= $d['id'] ?>"><?= csrf_field() ?><input type="hidden" name="move" value="up"><button class="btn btn-ghost btn-sm"<?= $i === 0 ? ' disabled' : '' ?>>↑</button></form>
          <form method="post" action="/settings/fields/<?= $d['id'] ?>"><?= csrf_field() ?><input type="hidden" name="move" value="down"><button class="btn btn-ghost btn-sm"<?= $i === count($rows) - 1 ? ' disabled' : '' ?>>↓</button></form>
          <button type="button" class="btn btn-ghost btn-sm" data-open="field-edit-dialog" data-action="/settings/fields/<?= $d['id'] ?>" data-fill="<?= esc(json_encode(['label' => $d['label'], 'options' => implode("\n", $d['options'] ?? []), 'required' => (bool) $d['required'], '_title' => 'Edit ' . $d['label']]), 'attr') ?>">Edit</button>
          <form method="post" action="/settings/fields/<?= $d['id'] ?>/delete" data-confirm="Delete field <?= esc($d['label'], 'attr') ?>?"><?= csrf_field() ?><button class="btn btn-ghost btn-sm text-danger"><?= icon('trash', 'h-3.5 w-3.5') ?></button></form>
        </div>
      </li>
    <?php endforeach ?>
    </ul>
  </div>
<?php endforeach ?>
</div>
<dialog id="field-dialog" class="modal"><form method="post" action="/settings/fields"><?= csrf_field() ?>
  <h2 class="card-title mb-3">New custom field</h2>
  <div class="grid gap-3 sm:grid-cols-2">
    <div class="field"><label class="label" for="f-entity">Record type</label><select class="select" id="f-entity" name="entity"><?php foreach ($entities as $e => $l): ?><option value="<?= $e ?>"<?= selected_if(old('entity') === $e) ?>><?= $l ?></option><?php endforeach ?></select></div>
    <div class="field"><label class="label" for="f-type">Field type</label><select class="select" id="f-type" name="type" data-field-type><?php foreach (['TEXT' => 'Text', 'NUMBER' => 'Number', 'DATE' => 'Date', 'SELECT' => 'Dropdown', 'CHECKBOX' => 'Checkbox'] as $t => $l): ?><option value="<?= $t ?>"<?= selected_if(old('type') === $t) ?>><?= $l ?></option><?php endforeach ?></select></div>
    <div class="field sm:col-span-2"><label class="label" for="f-label">Label</label><input class="input" id="f-label" name="label" value="<?= old_or('label') ?>" required placeholder="e.g. GST number"></div>
    <div class="field sm:col-span-2" data-options-field><label class="label" for="f-options">Dropdown options (one per line)</label><textarea class="textarea" id="f-options" name="options"><?= old_or('options') ?></textarea></div>
    <label class="flex items-center gap-2 text-[13px]"><input type="checkbox" name="required"<?= checked_if(old('required') === 'on') ?>> Required</label>
  </div>
  <div class="mt-3 flex justify-end gap-2"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary" type="submit">Add field</button></div>
</form></dialog>
<dialog id="field-edit-dialog" class="modal"><form method="post" action=""><?= csrf_field() ?>
  <h2 class="card-title mb-3" data-dialog-title>Edit field</h2>
  <div class="field"><label class="label" for="fe-label">Label</label><input class="input" id="fe-label" name="label" required></div>
  <div class="field"><label class="label" for="fe-options">Dropdown options (one per line; only for dropdown fields)</label><textarea class="textarea" id="fe-options" name="options"></textarea></div>
  <label class="mb-3 flex items-center gap-2 text-[13px]"><input type="checkbox" name="required"> Required</label>
  <div class="flex justify-end gap-2"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
</form></dialog>
<?= $this->endSection() ?>
