<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Companies', 'subtitle' => $total . ' compan' . ($total === 1 ? 'y' : 'ies'), 'actions' => '<button class="btn btn-primary" data-open="company-dialog">' . icon('plus') . 'New company</button>']) ?>
<?= view('partials/saved_views', ['entity' => 'COMPANY', 'views' => $views, 'exportHref' => '/export/companies']) ?>
<?php $extra = '<select class="select w-44" name="industry"><option value="">All industries</option>'; foreach ($industries as $i) { $extra .= '<option value="' . esc($i, 'attr') . '"' . selected_if(($p['industry'] ?? '') === $i) . '>' . esc($i) . '</option>'; } $extra .= '</select>'; ?>
<?= view('partials/list_toolbar', ['p' => $p, 'users' => $users, 'tags' => $tags, 'extra' => $extra]) ?>
<?php if (! $rows): ?>
  <div class="empty"><?= ! empty($p['q']) || ! empty($p['owner']) || ! empty($p['tag']) ? 'No companies match.' : 'No companies yet. Add your first company or import a CSV from Settings.' ?></div>
<?php else: ?>
<div class="card overflow-x-auto"><table class="table" data-testid="companies-table">
  <thead><tr><th><?= sort_link('Name', 'name') ?></th><th>Industry</th><th><?= sort_link('City', 'city') ?></th><th>Tags</th><th>Owner</th><th class="text-right">Contacts</th><th class="text-right">Open deals</th><th><?= sort_link('Updated', 'updated') ?></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $c): ?>
    <tr><td><a href="/companies/<?= $c['id'] ?>" class="font-medium text-primary hover:underline"><?= esc($c['name']) ?></a></td>
      <td><?= esc($c['industry'] ?? '—') ?></td><td><?= esc(trim(($c['city'] ?? '') . ($c['state'] ? ', ' . $c['state'] : '')) ?: '—') ?></td>
      <td><?= tag_badges(array_slice($c['tags'], 0, 3)) ?></td>
      <td><?= $c['owner_name'] ? '<span class="inline-flex items-center gap-1.5">' . avatar($c['owner_name'], $c['owner_color'], 22) . '<span class="text-xs">' . esc($c['owner_name']) . '</span></span>' : '<span class="text-xs muted">Unassigned</span>' ?></td>
      <td class="text-right tabular"><?= (int) $c['contact_count'] ?></td><td class="text-right tabular"><?= (int) $c['open_deal_count'] ?></td>
      <td class="text-xs muted"><?= relative_time($c['updated_at']) ?></td></tr>
  <?php endforeach ?>
  </tbody></table></div>
<?= view('partials/pagination', ['total' => $total, 'page' => $page, 'perPage' => $perPage]) ?>
<?php endif ?>
<dialog id="company-dialog" class="modal modal-lg"><form method="post" action="/companies" data-keep-enabled><?= csrf_field() ?>
  <h2 class="card-title mb-3">New company</h2>
  <?= view('partials/duplicates', ['href' => '/companies']) ?>
  <?= view('companies/_form', ['company' => null]) ?>
  <div class="mt-2 flex justify-end gap-2"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary" type="submit">Create company</button></div>
</form></dialog>
<?php if (! empty($p['new'])): ?><div hidden data-auto-open="company-dialog"></div><?php endif ?>
<?= $this->endSection() ?>
