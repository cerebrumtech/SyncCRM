<?php /** $contact (array|null), $users, $tags, $defs, $company (array|null), $me, $prefill */ $c = $contact ?? []; $ov = fn ($k, $d = '') => old_or($k, $c[$k] ?? $d); ?>
<div class="grid gap-3 sm:grid-cols-2">
  <div class="field"><label class="label" for="first_name">First name *</label><input class="input" id="first_name" name="first_name" value="<?= $ov('first_name') ?>" required autofocus></div>
  <div class="field"><label class="label" for="last_name">Last name</label><input class="input" id="last_name" name="last_name" value="<?= $ov('last_name') ?>"></div>
  <div class="field"><label class="label" for="email">Email</label><input class="input" id="email" name="email" type="email" value="<?= $ov('email') ?>"></div>
  <div class="field"><label class="label" for="phone">Phone</label><input class="input" id="phone" name="phone" value="<?= $ov('phone') ?>" placeholder="+91 98220 11111"></div>
  <div class="field"><label class="label" for="whatsapp_number">WhatsApp number</label><input class="input" id="whatsapp_number" name="whatsapp_number" value="<?= $ov('whatsapp_number') ?>" placeholder="same as phone if blank"></div>
  <div class="field"><label class="label" for="job_title">Job title</label><input class="input" id="job_title" name="job_title" value="<?= $ov('job_title') ?>"></div>
  <div class="field"><label class="label">Company</label><div data-picker="/api/search/companies" data-name="company_id" data-value="<?= esc(old('company_id') ?? $c['company_id'] ?? $prefill['company_id'] ?? '', 'attr') ?>" data-label="<?= esc(old('company_id') ? '' : ($company['name'] ?? $prefill['company_name'] ?? ''), 'attr') ?>" data-placeholder="Search companies…"></div></div>
  <div class="field"><label class="label" for="owner_id">Owner</label><select class="select" id="owner_id" name="owner_id"><?php $cur = old('owner_id') ?? $c['owner_id'] ?? $me['id']; foreach ($users as $u): ?><option value="<?= $u['id'] ?>"<?= selected_if((string) $cur === (string) $u['id']) ?>><?= esc($u['name']) ?></option><?php endforeach ?></select></div>
</div>
<?= view('partials/tag_input', ['tags' => $tags, 'value' => $c['tags'] ?? []]) ?>
<?php if ($defs): ?><div class="grid gap-3 sm:grid-cols-2"><?= view('partials/custom_fields', ['defs' => $defs, 'values' => $c['custom_fields'] ?? []]) ?></div><?php endif ?>
