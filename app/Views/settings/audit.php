<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Settings', 'subtitle' => 'Who changed what, and when']) ?>
<?= view('settings/_nav') ?>
<form class="mb-3 flex flex-wrap gap-2">
  <input class="input w-56" name="search" placeholder="Search label or action" value="<?= esc($q['search'] ?? '', 'attr') ?>">
  <select class="select w-40" name="entity"><option value="">All entities</option><?php foreach ($entities as $e): ?><option value="<?= esc($e, 'attr') ?>"<?= selected_if(($q['entity'] ?? '') === $e) ?>><?= esc($e) ?></option><?php endforeach ?></select>
  <select class="select w-44" name="actor"><option value="">Everyone</option><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"<?= selected_if(($q['actor'] ?? '') == $u['id']) ?>><?= esc($u['name']) ?></option><?php endforeach ?></select>
  <button class="btn btn-secondary" type="submit">Filter</button>
</form>
<div class="card overflow-x-auto"><table class="table">
  <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Entity</th><th>Record</th><th>Details</th></tr></thead>
  <tbody>
  <?php if (! $rows): ?><tr><td colspan="6" class="muted py-8 text-center">Nothing logged yet.</td></tr><?php endif ?>
  <?php foreach ($rows as $r): $actor = $userMap[$r['actor_id']] ?? null; $diff = []; foreach (($r['after_data'] ?? []) as $k => $v) { if (is_scalar($v) || $v === null) { $b = $r['before_data'][$k] ?? null; if ($b !== $v && (is_scalar($b) || $b === null)) { $diff[] = $k . ': ' . ($b === null || $b === '' ? '—' : (is_bool($b) ? ($b ? 'yes' : 'no') : $b)) . ' → ' . ($v === null || $v === '' ? '—' : (is_bool($v) ? ($v ? 'yes' : 'no') : $v)); } } } ?>
    <tr data-testid="audit-row"><td class="muted whitespace-nowrap" title="<?= esc(format_datetime($r['created_at']), 'attr') ?>"><?= relative_time($r['created_at']) ?></td>
      <td><?= $actor ? '<span class="inline-flex items-center gap-1.5">' . avatar($actor['name'], $actor['color'], 20) . esc($actor['name']) . '</span>' : '<span class="muted">System</span>' ?></td>
      <td><?= badge(str_replace('_', ' ', $r['action']), in_array($r['action'], ['delete', 'deactivate', 'lost'], true) ? 'danger' : (in_array($r['action'], ['create', 'won', 'invite'], true) ? 'success' : 'neutral')) ?></td>
      <td><?= esc($r['entity']) ?></td><td class="max-w-56 truncate"><?= esc($r['entity_label'] ?? '') ?></td>
      <td class="max-w-md text-xs muted"><?= esc(implode(' · ', array_slice($diff, 0, 6))) ?></td></tr>
  <?php endforeach ?>
  </tbody></table></div>
<?= view('partials/pagination', ['total' => $total, 'page' => $page, 'perPage' => $perPage]) ?>
<?= $this->endSection() ?>
