<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $cf = $company['custom_fields'] ?? []; ?>
<div class="mb-3 text-xs muted"><a href="/companies" class="hover:text-primary">Companies</a> / <?= esc($company['name']) ?></div>
<div class="mb-5 flex flex-wrap items-start justify-between gap-3">
  <div class="flex items-center gap-3"><span class="flex h-11 w-11 items-center justify-center rounded-lg bg-primary-50 text-primary"><?= icon('companies', 'h-5 w-5') ?></span>
    <div><h1 class="text-xl font-semibold text-navy"><?= esc($company['name']) ?></h1>
      <p class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[13px] muted"><?php if ($company['industry']): ?><span><?= esc($company['industry']) ?></span><?php endif ?><?php if ($company['city']): ?><span><?= esc($company['city']) ?><?= $company['state'] ? ', ' . esc($company['state']) : '' ?></span><?php endif ?><?= tag_badges($company['tags'] ?? []) ?></p></div></div>
  <div class="flex flex-wrap items-center gap-2">
    <?= view('partials/activity_quick', ['linked' => ['company_id' => $company['id'], 'company_label' => $company['name']]]) ?>
    <a class="btn btn-secondary" href="/contacts?company=<?= $company['id'] ?>&new=1"><?= icon('plus') ?> Contact</a>
    <?php if ($canEdit): ?><button class="btn btn-secondary" data-open="company-dialog">Edit</button><?php endif ?>
    <div class="relative"><button class="btn btn-secondary" data-dropdown><?= icon('chevron-down') ?></button>
      <div class="dropdown" hidden>
        <?php if ($canEdit): ?><button type="button" data-open="share-dialog"><?= icon('share', 'inline h-3.5 w-3.5') ?> Share…</button><button type="button" data-open="merge-dialog"><?= icon('merge', 'inline h-3.5 w-3.5') ?> Merge another company into this…</button><?php endif ?>
        <?php if ($canDelete): ?><form method="post" action="/companies/<?= $company['id'] ?>/delete" data-confirm="Delete <?= esc($company['name'], 'attr') ?>? Contacts and deals stay but lose the link."><?= csrf_field() ?><button class="text-danger"><?= icon('trash', 'inline h-3.5 w-3.5') ?> Delete</button></form><?php endif ?>
      </div></div>
  </div>
</div>
<div class="grid gap-5 lg:grid-cols-[340px_1fr]">
  <div class="card card-pad space-y-3 text-[13px]">
    <h2 class="card-title">Details</h2>
    <div class="grid grid-cols-[90px_1fr] gap-y-2">
      <span class="muted">Phone</span><span><?= esc($company['phone'] ?? '—') ?></span>
      <span class="muted">Email</span><span class="break-all"><?= esc($company['email'] ?? '—') ?></span>
      <span class="muted">Website</span><span class="break-all"><?= $company['website'] ? '<a class="link" target="_blank" rel="noopener" href="' . esc(str_starts_with($company['website'], 'http') ? $company['website'] : 'https://' . $company['website'], 'attr') . '">' . esc($company['website']) . '</a>' : '—' ?></span>
      <span class="muted">Address</span><span><?= esc(implode(', ', array_filter([$company['address_line'], $company['city'], $company['state'], $company['postal_code'], $company['country']])) ?: '—') ?></span>
      <span class="muted">Owner</span><span><?= $owner ? '<span class="inline-flex items-center gap-1.5">' . avatar($owner['name'], $owner['color'], 20) . esc($owner['name']) . '</span>' : 'Unassigned' ?></span>
      <span class="muted">Created</span><span><?= format_date($company['created_at']) ?></span>
      <?php foreach ($defs as $d): ?><span class="muted"><?= esc($d['label']) ?></span><span><?= esc(\App\Libraries\CustomFields::display($d, $cf[$d['field_key']] ?? null)) ?></span><?php endforeach ?>
    </div>
    <?php if ($company['description']): ?><p class="whitespace-pre-line border-t border-line-100 pt-2 text-ink-700"><?= esc($company['description']) ?></p><?php endif ?>
  </div>
  <?php
  $contactsHtml = $contacts ? '<table class="table"><thead><tr><th>Name</th><th>Title</th><th>Phone</th><th>Email</th></tr></thead><tbody>' . implode('', array_map(fn ($c) => '<tr><td><a class="font-medium text-primary hover:underline" href="/contacts/' . $c['id'] . '">' . esc(full_name($c)) . '</a></td><td>' . esc($c['job_title'] ?? '—') . '</td><td class="tabular">' . esc($c['phone'] ?? '—') . '</td><td>' . esc($c['email'] ?? '—') . '</td></tr>', $contacts)) . '</tbody></table>' : '<p class="muted text-[13px]">No contacts yet.</p>';
  ?>
  <?= view('partials/record_panels', ['entity' => 'COMPANY', 'record' => $company, 'extraTabs' => ['contacts' => ['Contacts', count($contacts), $contactsHtml], 'deals' => ['Deals', count($deals), view('partials/deals_table', ['deals' => $deals, 'newParams' => '&company_id=' . $company['id']])]]]) ?>
</div>
<?php if ($canEdit): ?>
<dialog id="company-dialog" class="modal modal-lg"><form method="post" action="/companies/<?= $company['id'] ?>" data-keep-enabled><?= csrf_field() ?>
  <h2 class="card-title mb-3">Edit company</h2>
  <?= view('companies/_form', ['company' => $company]) ?>
  <div class="mt-2 flex justify-end gap-2"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary" type="submit">Save changes</button></div>
</form></dialog>
<dialog id="merge-dialog" class="modal"><form method="post" action="/companies/<?= $company['id'] ?>/merge"><?= csrf_field() ?>
  <h2 class="card-title mb-1">Merge into <?= esc($company['name']) ?></h2>
  <p class="mb-3 text-[13px] muted">Pick the duplicate to merge away. Its contacts, deals, activities, notes and files move here; then it is deleted.</p>
  <div class="field"><label class="label">Company to merge away</label><div data-picker="/api/search/companies" data-name="source_id" data-placeholder="Search the duplicate to merge away…"></div></div>
  <div class="flex justify-end gap-2"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-danger" type="submit">Merge</button></div>
</form></dialog>
<?= view('partials/share_dialog', ['entity' => 'COMPANY', 'record' => $company]) ?>
<?php endif ?>
<?= view('partials/activity_dialog', ['linked' => ['company_id' => $company['id'], 'company_label' => $company['name']]]) ?>
<?= $this->endSection() ?>
