<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Companies', 'subtitle' => $total . ' compan' . ($total === 1 ? 'y' : 'ies'), 'actions' => '<button class="btn btn-primary" data-open="company-dialog">' . icon('plus') . 'New company</button>']) ?>
<?= view('partials/saved_views', ['entity' => 'COMPANY', 'views' => $views, 'exportHref' => '/export/companies']) ?>
<?php $extra = '<select class="select w-44" name="industry"><option value="">All industries</option>'; foreach ($industries as $i) { $extra .= '<option value="' . esc($i, 'attr') . '"' . selected_if(($p['industry'] ?? '') === $i) . '>' . esc($i) . '</option>'; } $extra .= '</select>'; $extra .= '<div class="ml-auto">' . view('partials/view_toggle', ['current' => $view === 'sheet' ? 'sheet' : '']) . '</div>'; ?>
<?= view('partials/list_toolbar', ['p' => $p, 'users' => $users, 'tags' => $tags, 'extra' => $extra]) ?>
<?php if (! $rows): ?>
  <div class="empty"><?= ! empty($p['q']) || ! empty($p['owner']) || ! empty($p['tag']) ? 'No companies match.' : 'No companies yet. Add your first company or import a CSV from Settings.' ?></div>
<?php else: ?>
<?php if ($view === 'sheet'): ?>
<?php
$dash = fn ($v) => $v !== null && $v !== '' ? esc($v) : '<span class="muted">—</span>';
$num  = fn ($v) => $v !== null && $v !== '' ? esc(format_inr($v)) : '<span class="muted">—</span>';
$cols = [
  ['key' => 'name',      'label' => 'Company',   'render' => fn ($c) => '<a href="/companies/' . $c['id'] . '" class="font-medium text-primary hover:underline">' . esc($c['name']) . '</a>'],
  ['key' => 'industry',  'label' => 'Industry',  'render' => fn ($c) => $dash($c['industry'] ?? null)],
  ['key' => 'city',      'label' => 'City',      'render' => fn ($c) => $dash($c['city'] ?? null)],
  ['key' => 'district',  'label' => 'District',  'render' => fn ($c) => $dash($c['district'] ?? null)],
  ['key' => 'state',     'label' => 'State',     'render' => fn ($c) => $dash($c['state'] ?? null)],
  ['key' => 'phone',     'label' => 'Phone',     'class' => 'tabular', 'render' => fn ($c) => $dash($c['phone'] ?? null)],
  ['key' => 'alt',       'label' => 'Alt phone', 'class' => 'tabular', 'render' => fn ($c) => $dash($c['alt_phone'] ?? null)],
  ['key' => 'email',     'label' => 'Email',     'render' => fn ($c) => $dash($c['email'] ?? null)],
  ['key' => 'website',   'label' => 'Website',   'render' => fn ($c) => $dash($c['website'] ?? null)],
  ['key' => 'branches',  'label' => 'Branches',  'class' => 'text-right tabular', 'render' => fn ($c) => $dash($c['branches'] ?? null)],
  ['key' => 'deposits',  'label' => 'Deposits (Cr)', 'class' => 'text-right tabular', 'render' => fn ($c) => $dash($c['deposits_cr'] ?? null)],
  ['key' => 'loanbook',  'label' => 'Loan book (Cr)','class' => 'text-right tabular', 'render' => fn ($c) => $dash($c['loan_book_cr'] ?? null)],
  ['key' => 'loancust',  'label' => 'Loan customers','class' => 'text-right tabular', 'render' => fn ($c) => $dash($c['loan_customers'] ?? null)],
  ['key' => 'tags',      'label' => 'Tags',      'render' => fn ($c) => $c['tags'] ? tag_badges(array_slice($c['tags'], 0, 4)) : '<span class="muted">—</span>'],
  ['key' => 'owner',     'label' => 'Owner',     'render' => fn ($c) => $c['owner_name'] ? esc($c['owner_name']) : '<span class="muted">Unassigned</span>'],
  ['key' => 'contacts',  'label' => 'Contacts',  'class' => 'text-right tabular', 'render' => fn ($c) => (int) $c['contact_count']],
  ['key' => 'opendeals', 'label' => 'Open deals','class' => 'text-right tabular', 'render' => fn ($c) => (int) $c['open_deal_count']],
  ['key' => 'updated',   'label' => 'Updated',   'class' => 'text-xs muted', 'render' => fn ($c) => relative_time($c['updated_at'])],
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
<?= view('partials/sheet', ['cols' => $cols, 'rows' => $rows, 'entity' => 'COMPANY']) ?>
<?= view('partials/pagination', ['total' => $total, 'page' => $page, 'perPage' => $perPage]) ?>
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
<?php endif ?>
<dialog id="company-dialog" class="modal modal-lg"><form method="post" action="/companies" data-keep-enabled><?= csrf_field() ?>
  <h2 class="card-title mb-3">New company</h2>
  <?= view('partials/duplicates', ['href' => '/companies']) ?>
  <?= view('companies/_form', ['company' => null]) ?>
  <div class="mt-2 flex justify-end gap-2"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary" type="submit">Create company</button></div>
</form></dialog>
<?php if (! empty($p['new'])): ?><div hidden data-auto-open="company-dialog"></div><?php endif ?>
<?= $this->endSection() ?>
