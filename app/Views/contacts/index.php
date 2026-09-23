<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Contacts', 'subtitle' => $total . ' contact' . ($total === 1 ? '' : 's'), 'actions' => '<button class="btn btn-primary" data-open="contact-dialog">' . icon('plus') . 'New contact</button>']) ?>
<?= view('partials/saved_views', ['entity' => 'CONTACT', 'views' => $views, 'exportHref' => '/export/contacts']) ?>
<?= view('partials/list_toolbar', ['p' => $p, 'users' => $users, 'tags' => $tags]) ?>
<?php if (! $rows): ?>
  <div class="empty"><?= ! empty($p['q']) || ! empty($p['owner']) || ! empty($p['tag']) ? 'No contacts match. Try a different search or filter.' : 'No contacts yet. Add your first contact or import a CSV from Settings.' ?></div>
<?php else: ?>
<div class="card overflow-x-auto"><table class="table" data-testid="contacts-table">
  <thead><tr><th><?= sort_link('Name', 'name') ?></th><th><?= sort_link('Company', 'company') ?></th><th>Phone</th><th>Email</th><th>Tags</th><th>Owner</th><th class="text-right">Deals</th><th><?= sort_link('Updated', 'updated') ?></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $c): ?>
    <tr>
      <td><a href="/contacts/<?= $c['id'] ?>" class="font-medium text-primary hover:underline"><?= esc(full_name($c)) ?></a><?php if ($c['job_title']): ?><div class="text-xs muted"><?= esc($c['job_title']) ?></div><?php endif ?></td>
      <td><?= $c['company_id'] ? '<a href="/companies/' . $c['company_id'] . '" class="hover:text-primary">' . esc($c['company_name']) . '</a>' : '<span class="muted">—</span>' ?></td>
      <td class="tabular"><?= $c['phone'] ? esc($c['phone']) : '<span class="muted">—</span>' ?></td>
      <td><?= $c['email'] ? esc($c['email']) : '<span class="muted">—</span>' ?></td>
      <td><?= tag_badges(array_slice($c['tags'], 0, 3)) ?><?= count($c['tags']) > 3 ? ' <span class="text-xs muted">+' . (count($c['tags']) - 3) . '</span>' : '' ?></td>
      <td><?= $c['owner_name'] ? '<span class="inline-flex items-center gap-1.5">' . avatar($c['owner_name'], $c['owner_color'], 22) . '<span class="text-xs">' . esc($c['owner_name']) . '</span></span>' : '<span class="text-xs muted">Unassigned</span>' ?></td>
      <td class="text-right tabular"><?= (int) $c['deal_count'] ?></td>
      <td class="text-xs muted"><?= relative_time($c['updated_at']) ?></td>
    </tr>
  <?php endforeach ?>
  </tbody></table></div>
<?= view('partials/pagination', ['total' => $total, 'page' => $page, 'perPage' => $perPage]) ?>
<?php endif ?>

<dialog id="contact-dialog" class="modal modal-lg"><form method="post" action="/contacts" data-keep-enabled><?= csrf_field() ?>
  <h2 class="card-title mb-3">New contact</h2>
  <?= view('partials/duplicates', ['href' => '/contacts']) ?>
  <?= view('contacts/_form', ['contact' => null, 'company' => null]) ?>
  <div class="mt-2 flex justify-end gap-2"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary" type="submit">Create contact</button></div>
</form></dialog>
<?= $this->endSection() ?>
