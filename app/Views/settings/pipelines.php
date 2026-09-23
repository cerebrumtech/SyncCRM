<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Settings', 'subtitle' => 'Pipelines and stages. Required fields must be filled before a deal can enter a stage.', 'actions' => '<button class="btn btn-primary" data-open="pipeline-dialog">' . icon('plus') . 'New pipeline</button>']) ?>
<?= view('settings/_nav') ?>
<div class="space-y-5">
<?php foreach ($pipelines as $pl): ?>
  <div class="card" data-testid="pipeline-card" data-name="<?= esc($pl['name'], 'attr') ?>">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line-100 px-4 py-3">
      <form method="post" action="/settings/pipelines/<?= $pl['id'] ?>" class="flex items-center gap-2"><?= csrf_field() ?><input class="input w-56 font-semibold" name="name" value="<?= esc($pl['name'], 'attr') ?>" aria-label="Pipeline name"><button class="btn btn-secondary btn-sm" type="submit">Rename</button><?= $pl['is_default'] ? badge('Default', 'info') : '' ?></form>
      <div class="flex items-center gap-2">
        <?php if (! $pl['is_default']): ?><form method="post" action="/settings/pipelines/<?= $pl['id'] ?>"><?= csrf_field() ?><input type="hidden" name="make_default" value="1"><button class="btn btn-ghost btn-sm" type="submit">Make default</button></form><?php endif ?>
        <button class="btn btn-secondary btn-sm" data-open="stage-dialog-<?= $pl['id'] ?>"><?= icon('plus') ?> Add stage</button>
        <form method="post" action="/settings/pipelines/<?= $pl['id'] ?>/delete" data-confirm="Delete pipeline <?= esc($pl['name'], 'attr') ?>?"><?= csrf_field() ?><button class="btn btn-ghost btn-sm text-danger" type="submit"><?= icon('trash') ?></button></form>
      </div>
    </div>
    <table class="table"><thead><tr><th class="w-8"></th><th>Stage</th><th>Type</th><th class="text-right">Probability</th><th>Required before entering</th><th class="text-right">Deals</th><th></th></tr></thead><tbody>
    <?php foreach ($pl['stages'] as $i => $s): $kind = $s['is_won'] ? 'WON' : ($s['is_lost'] ? 'LOST' : 'OPEN'); ?>
      <tr data-testid="stage-row">
        <td><span class="inline-block h-3 w-3 rounded-full" style="background:<?= esc($s['color'], 'attr') ?>"></span></td>
        <td class="font-medium"><?= esc($s['name']) ?></td>
        <td><?= $kind === 'WON' ? badge('Won', 'success') : ($kind === 'LOST' ? badge('Lost', 'danger') : badge('Open')) ?></td>
        <td class="text-right tabular"><?= $s['probability'] ?>%</td>
        <td class="text-xs muted"><?= $s['required_fields'] ? esc(implode(', ', array_map(fn ($f) => $required[$f] ?? $f, $s['required_fields']))) : '—' ?></td>
        <td class="text-right tabular"><?= $counts[$s['id']] ?? 0 ?></td>
        <td class="whitespace-nowrap text-right">
          <form method="post" action="/settings/stages/<?= $s['id'] ?>/move" class="inline"><?= csrf_field() ?><input type="hidden" name="dir" value="up"><button class="btn btn-ghost btn-sm" title="Move up"<?= $i === 0 ? ' disabled' : '' ?>>↑</button></form>
          <form method="post" action="/settings/stages/<?= $s['id'] ?>/move" class="inline"><?= csrf_field() ?><input type="hidden" name="dir" value="down"><button class="btn btn-ghost btn-sm" title="Move down"<?= $i === count($pl['stages']) - 1 ? ' disabled' : '' ?>>↓</button></form>
          <button type="button" class="btn btn-ghost btn-sm" data-open="stage-edit-dialog" data-action="/settings/stages/<?= $s['id'] ?>" data-fill="<?= esc(json_encode(['name' => $s['name'], 'kind' => $kind, 'probability' => $s['probability'], 'color' => $s['color'], '_required' => $s['required_fields']]), 'attr') ?>" data-required="<?= esc(json_encode($s['required_fields']), 'attr') ?>">Edit</button>
          <form method="post" action="/settings/stages/<?= $s['id'] ?>/delete" class="inline" data-confirm="Delete stage <?= esc($s['name'], 'attr') ?>?"><?= csrf_field() ?><button class="btn btn-ghost btn-sm text-danger" title="Delete"><?= icon('trash', 'h-3.5 w-3.5') ?></button></form>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody></table>
  </div>
  <dialog id="stage-dialog-<?= $pl['id'] ?>" class="modal"><form method="post" action="/settings/pipelines/<?= $pl['id'] ?>/stages"><?= csrf_field() ?>
    <h2 class="card-title mb-3">Add stage to <?= esc($pl['name']) ?></h2>
    <?= view('settings/_stage_fields', ['required' => $required, 'idp' => 'add-' . $pl['id']]) ?>
    <div class="flex justify-end gap-2"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary" type="submit">Add stage</button></div>
  </form></dialog>
<?php endforeach ?>
</div>
<dialog id="stage-edit-dialog" class="modal"><form method="post" action=""><?= csrf_field() ?>
  <h2 class="card-title mb-3">Edit stage</h2>
  <?= view('settings/_stage_fields', ['required' => $required, 'idp' => 'edit']) ?>
  <div class="flex justify-end gap-2"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary" type="submit">Save stage</button></div>
</form></dialog>
<dialog id="pipeline-dialog" class="modal"><form method="post" action="/settings/pipelines"><?= csrf_field() ?>
  <h2 class="card-title mb-3">New pipeline</h2>
  <div class="field"><label class="label" for="pipeline-name">Name</label><input class="input" id="pipeline-name" name="name" value="<?= old_or('name') ?>" placeholder="e.g. Renewals" required></div>
  <p class="hint mb-3">Starts with New → In Progress → Won → Lost; edit the stages afterwards.</p>
  <div class="flex justify-end gap-2"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary" type="submit">Create pipeline</button></div>
</form></dialog>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
document.addEventListener("click", (e) => { const b = e.target.closest("[data-open=stage-edit-dialog]"); if (!b) return; const req = JSON.parse(b.getAttribute("data-required") || "[]"); document.querySelectorAll("#stage-edit-dialog input[name='required_fields[]']").forEach((cb) => { cb.checked = req.includes(cb.value); }); });
</script>
<?= $this->endSection() ?>
