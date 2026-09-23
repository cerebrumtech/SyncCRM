<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Settings', 'subtitle' => 'Import contacts or companies from a CSV file']) ?>
<?= view('settings/_nav') ?>
<?php if ($summary): ?>
  <div class="card card-pad mb-5" data-testid="import-summary">
    <h2 class="card-title mb-2">Import finished</h2>
    <p class="text-[13px]"><b><?= $summary['created'] ?></b> created · <b><?= $summary['updated'] ?></b> updated · <b><?= $summary['skipped'] ?></b> skipped<?= $summary['errors'] ? ' · <b class="text-danger">' . count($summary['errors']) . '</b> errors' : '' ?><?= ! empty($summary['noPhoneKey']) ? ' · <b class="text-warning-fg">' . $summary['noPhoneKey'] . '</b> with an unreadable phone number (imported, but duplicates of these will not be detected)' : '' ?>. <a class="link" href="<?= $summary['entity'] === 'CONTACT' ? '/contacts' : '/companies' ?>">Open the list</a>.</p>
    <?php if ($summary['errors']): ?><ul class="mt-2 list-disc pl-5 text-xs text-danger"><?php foreach ($summary['errors'] as $e): ?><li><?= esc($e) ?></li><?php endforeach ?></ul><?php endif ?>
  </div>
<?php endif ?>
<?php if (! $pending): ?>
<div class="card card-pad max-w-2xl">
  <h2 class="card-title mb-1">Step 1 · Upload a CSV</h2>
  <p class="mb-3 text-[13px] muted">The first row must contain column headings. You will map the columns in the next step. Up to 5,000 rows per file.</p>
  <form method="post" action="/settings/import/upload" enctype="multipart/form-data"><?= csrf_field() ?>
    <div class="grid gap-3 sm:grid-cols-2">
      <div class="field"><label class="label" for="imp-entity">Import</label><select class="select" id="imp-entity" name="entity"><option value="CONTACT">Contacts</option><option value="COMPANY">Companies</option></select></div>
      <div class="field"><label class="label" for="imp-file">CSV file</label><input class="text-[13px]" id="imp-file" type="file" name="file" accept=".csv,text/csv" required></div>
    </div>
    <button class="btn btn-primary" type="submit"><?= icon('upload') ?> Upload and continue</button>
  </form>
</div>
<?php else: ?>
<form method="post" action="/settings/import/run" class="card card-pad" data-testid="import-map"><?= csrf_field() ?>
  <h2 class="card-title mb-1">Step 2 · Map columns</h2>
  <p class="mb-3 text-[13px] muted"><b><?= esc($pending['name']) ?></b> · <?= $pending['count'] ?> rows · importing <?= $pending['entity'] === 'CONTACT' ? 'contacts' : 'companies' ?>. Choose which field each column goes into; leave "Skip" for columns you don't need.</p>
  <div class="overflow-x-auto"><table class="table"><thead><tr><th>CSV column</th><th>Sample values</th><th>Import into</th></tr></thead><tbody>
  <?php foreach ($pending['header'] as $i => $h): ?>
    <tr><td class="font-medium"><?= esc($h) ?></td><td class="text-xs muted"><?= esc(implode(' · ', array_filter(array_map(fn ($r) => $r[$i] ?? '', $pending['sample'])))) ?></td>
      <td><select class="select w-64" name="map[<?= $i ?>]"><option value="">Skip</option><?php foreach ($pending['targets'] as $k => $l): ?><option value="<?= esc($k, 'attr') ?>"<?= selected_if(($pending['guess'][$i] ?? '') === $k) ?>><?= esc($l) ?></option><?php endforeach ?></select></td></tr>
  <?php endforeach ?>
  </tbody></table></div>
  <div class="mt-4 flex flex-wrap items-end gap-3">
    <div class="field mb-0"><label class="label" for="imp-dup">When a matching record exists (<?= $pending['entity'] === 'CONTACT' ? 'same email or phone' : 'same name' ?>)</label>
      <select class="select w-72" id="imp-dup" name="on_duplicate"><option value="skip">Skip the row</option><option value="update">Update the existing record (fill blanks, add tags)</option><option value="create">Create a duplicate anyway</option></select></div>
    <button class="btn btn-primary" type="submit" data-no-disable>Run import</button>
    <button class="btn btn-secondary" type="submit" name="cancel" value="1" formnovalidate data-no-disable>Cancel</button>
  </div>
</form>
<?php endif ?>
<?= $this->endSection() ?>
