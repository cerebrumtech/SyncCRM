<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Contacts', 'subtitle' => $total . ' contact' . ($total === 1 ? '' : 's'), 'actions' => '<button class="btn btn-primary" data-open="contact-dialog">' . icon('plus') . 'New contact</button>']) ?>
<?= view('partials/saved_views', ['entity' => 'CONTACT', 'views' => $views, 'exportHref' => '/export/contacts']) ?>
<?= view('partials/list_toolbar', ['p' => $p, 'users' => $users, 'tags' => $tags,
    'extra' => '<div class="ml-auto">' . view('partials/view_toggle', ['current' => $view === 'sheet' ? 'sheet' : '']) . '</div>']) ?>
<?php if (! $rows): ?>
  <div class="empty"><?= ! empty($p['q']) || ! empty($p['owner']) || ! empty($p['tag']) ? 'No contacts match. Try a different search or filter.' : 'No contacts yet. Add your first contact or import a CSV from Settings.' ?></div>
<?php else: ?>
<?php if ($view === 'sheet'): ?>
<?php
$dash = fn ($v) => $v !== null && $v !== '' ? esc($v) : '<span class="muted">—</span>';
$cols = [
  ['key' => 'name',    'label' => 'Name',     'render' => fn ($c) => '<a href="/contacts/' . $c['id'] . '" class="font-medium text-primary hover:underline">' . esc(full_name($c)) . '</a>'],
  ['key' => 'company', 'label' => 'Company',  'render' => fn ($c) => $c['company_id'] ? '<a href="/companies/' . $c['company_id'] . '" class="hover:text-primary">' . esc($c['company_name']) . '</a>' : '<span class="muted">—</span>'],
  ['key' => 'title',   'label' => 'Job title','render' => fn ($c) => $dash($c['job_title'] ?? null)],
  ['key' => 'phone',   'label' => 'Phone',    'class' => 'tabular', 'render' => fn ($c) => $dash($c['phone'] ?? null)],
  ['key' => 'wa',      'label' => 'WhatsApp', 'class' => 'tabular', 'render' => fn ($c) => $dash($c['whatsapp_number'] ?? null)],
  ['key' => 'alt',     'label' => 'Alt phone','class' => 'tabular', 'render' => fn ($c) => $dash($c['alt_phone'] ?? null)],
  ['key' => 'email',   'label' => 'Email',    'render' => fn ($c) => $dash($c['email'] ?? null)],
  ['key' => 'tags',    'label' => 'Tags',     'render' => fn ($c) => $c['tags'] ? tag_badges(array_slice($c['tags'], 0, 4)) : '<span class="muted">—</span>'],
  ['key' => 'owner',   'label' => 'Owner',    'render' => fn ($c) => $c['owner_name'] ? esc($c['owner_name']) : '<span class="muted">Unassigned</span>'],
  ['key' => 'deals',   'label' => 'Deals',    'class' => 'text-right tabular', 'render' => fn ($c) => (int) $c['deal_count']],
  ['key' => 'created', 'label' => 'Created',  'class' => 'text-xs muted', 'render' => fn ($c) => format_date($c['created_at'] ?? null)],
  ['key' => 'updated', 'label' => 'Updated',  'class' => 'text-xs muted', 'render' => fn ($c) => relative_time($c['updated_at'])],
];
foreach ($defs as $d) {
    $k = $d['field_key'];
    $cols[] = ['key' => 'cf_' . $k, 'label' => $d['label'], 'render' => function ($c) use ($k, $dash) {
        $cf = is_array($c['custom_fields'] ?? null) ? $c['custom_fields'] : json_decode((string) ($c['custom_fields'] ?? ''), true);
        $v = is_array($cf) ? ($cf[$k] ?? null) : null;
        return $dash(is_bool($v) ? ($v ? 'Yes' : 'No') : $v);
    }];
}
?>
<?= view('partials/sheet', ['cols' => $cols, 'rows' => $rows, 'entity' => 'CONTACT']) ?>
<?= view('partials/pagination', ['total' => $total, 'page' => $page, 'perPage' => $perPage]) ?>
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
<?php endif ?>

<dialog id="contact-dialog" class="modal modal-lg"><form method="post" action="/contacts" data-keep-enabled><?= csrf_field() ?>
  <h2 class="card-title mb-3">New contact</h2>
  <?= view('partials/duplicates', ['href' => '/contacts']) ?>
  <?= view('contacts/_form', ['contact' => null, 'company' => null]) ?>
  <div class="mt-2 flex justify-end gap-2"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary" type="submit">Create contact</button></div>
</form></dialog>
<?php if (! empty($p['new'])): ?><div hidden data-auto-open="contact-dialog"></div><?php endif ?>
<?= $this->endSection() ?>
