<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $name = full_name($contact); $cf = $contact['custom_fields'] ?? []; ?>
<div class="mb-3 text-xs muted"><a href="/contacts" class="hover:text-primary">Contacts</a> / <?= esc($name) ?></div>
<div class="mb-5 flex flex-wrap items-start justify-between gap-3">
  <div class="flex items-center gap-3"><?= avatar($name, $owner['color'] ?? '#0068FF', 44) ?>
    <div><h1 class="text-xl font-semibold text-navy"><?= esc($name) ?></h1>
      <p class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[13px] muted"><?php if ($contact['job_title']): ?><span><?= esc($contact['job_title']) ?></span><?php endif ?><?php if ($company): ?><a class="hover:text-primary" href="/companies/<?= $company['id'] ?>"><?= icon('companies', 'inline h-3.5 w-3.5') ?> <?= esc($company['name']) ?></a><?php endif ?><?= tag_badges($contact['tags'] ?? []) ?></p></div></div>
  <div class="flex flex-wrap items-center gap-2">
    <?= view('partials/activity_quick', ['linked' => ['contact_id' => $contact['id'], 'contact_label' => $name, 'company_id' => $contact['company_id'], 'company_label' => $company['name'] ?? '']]) ?>
    <?php if ($canEdit): ?><button class="btn btn-secondary" data-open="contact-dialog">Edit</button><?php endif ?>
    <div class="relative"><button class="btn btn-secondary" data-dropdown><?= icon('chevron-down') ?></button>
      <div class="dropdown" hidden>
        <?php if ($canEdit): ?><button type="button" data-open="share-dialog"><?= icon('share', 'inline h-3.5 w-3.5') ?> Share…</button><button type="button" data-open="merge-dialog"><?= icon('merge', 'inline h-3.5 w-3.5') ?> Merge another contact into this…</button><?php endif ?>
        <?php if ($canDelete): ?><form method="post" action="/contacts/<?= $contact['id'] ?>/delete" data-confirm="Delete <?= esc($name, 'attr') ?>? Deals stay but lose the link; notes, files and activities on this contact are removed."><?= csrf_field() ?><button class="text-danger"><?= icon('trash', 'inline h-3.5 w-3.5') ?> Delete</button></form><?php endif ?>
      </div></div>
  </div>
</div>
<div class="grid gap-5 lg:grid-cols-[340px_1fr]">
  <div class="card card-pad space-y-3 text-[13px]">
    <h2 class="card-title">Details</h2>
    <div class="grid grid-cols-[90px_1fr] gap-y-2">
      <span class="muted">Phone</span><span><?= $contact['phone'] ? '<a href="tel:' . esc($contact['phone'], 'attr') . '" class="hover:text-primary">' . esc($contact['phone']) . '</a>' : '—' ?></span>
      <span class="muted">WhatsApp</span><span><?= esc($contact['whatsapp_number'] ?? $contact['phone'] ?? '—') ?></span>
      <span class="muted">Email</span><span class="break-all"><?= $contact['email'] ? '<a href="mailto:' . esc($contact['email'], 'attr') . '" class="hover:text-primary">' . esc($contact['email']) . '</a>' : '—' ?></span>
      <span class="muted">Owner</span><span><?= $owner ? '<span class="inline-flex items-center gap-1.5">' . avatar($owner['name'], $owner['color'], 20) . esc($owner['name']) . '</span>' : 'Unassigned' ?></span>
      <span class="muted">Created</span><span><?= format_date($contact['created_at']) ?></span>
      <?php foreach ($defs as $d): ?><span class="muted"><?= esc($d['label']) ?></span><span><?= esc(\App\Libraries\CustomFields::display($d, $cf[$d['field_key']] ?? null)) ?></span><?php endforeach ?>
    </div>
    <?php if ($shares): ?><div class="border-t border-line-100 pt-2 text-xs muted">Shared with <?= count($shares) ?> <?= count($shares) === 1 ? 'person/team' : 'people/teams' ?></div><?php endif ?>
  </div>
  <?= view('partials/record_panels', ['entity' => 'CONTACT', 'record' => $contact, 'extraTabs' => ['deals' => ['Deals', count($deals), view('partials/deals_table', ['deals' => $deals, 'newParams' => '&contact_id=' . $contact['id']])]]]) ?>
</div>
<?php if ($canEdit): ?>
<dialog id="contact-dialog" class="modal modal-lg"><form method="post" action="/contacts/<?= $contact['id'] ?>" data-keep-enabled><?= csrf_field() ?>
  <h2 class="card-title mb-3">Edit contact</h2>
  <?= view('contacts/_form', ['contact' => $contact, 'company' => $company, 'prefill' => []]) ?>
  <div class="mt-2 flex justify-end gap-2"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary" type="submit">Save changes</button></div>
</form></dialog>
<dialog id="merge-dialog" class="modal"><form method="post" action="/contacts/<?= $contact['id'] ?>/merge"><?= csrf_field() ?>
  <h2 class="card-title mb-1">Merge into <?= esc($name) ?></h2>
  <p class="mb-3 text-[13px] muted">Pick the duplicate to merge away. Its deals, activities, notes and files move here; blank fields here are filled from it; then it is deleted.</p>
  <div class="field"><label class="label">Contact to merge away</label><div data-picker="/api/search/contacts" data-name="source_id" data-placeholder="Search the duplicate to merge away…"></div></div>
  <div class="flex justify-end gap-2"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-danger" type="submit">Merge</button></div>
</form></dialog>
<?= view('partials/share_dialog', ['entity' => 'CONTACT', 'record' => $contact]) ?>
<?php endif ?>
<?= view('partials/activity_dialog', ['linked' => ['contact_id' => $contact['id'], 'contact_label' => $name, 'company_id' => $contact['company_id'], 'company_label' => $company['name'] ?? '']]) ?>
<?= $this->endSection() ?>
