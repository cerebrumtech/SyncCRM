<?php $c = $company ?? []; $ov = fn ($k, $d = '') => old_or($k, $c[$k] ?? $d); ?>
<div class="grid gap-3 sm:grid-cols-2">
  <div class="field sm:col-span-2"><label class="label" for="name">Company name *</label><input class="input" id="name" name="name" value="<?= $ov('name') ?>" required autofocus></div>
  <div class="field"><label class="label" for="industry">Industry</label><input class="input" id="industry" name="industry" list="industry-suggestions" value="<?= $ov('industry') ?>"><datalist id="industry-suggestions"><?php foreach (['Co-operative Credit Society', 'Microfinance', 'NBFC', 'Bank', 'Fintech', 'Other'] as $i): ?><option value="<?= $i ?>"><?php endforeach ?></datalist></div>
  <div class="field"><label class="label" for="website">Website</label><input class="input" id="website" name="website" value="<?= $ov('website') ?>" placeholder="https://"></div>
  <div class="field"><label class="label" for="phone">Phone</label><input class="input" id="phone" name="phone" value="<?= $ov('phone') ?>"></div>
  <div class="field"><label class="label" for="email">Email</label><input class="input" id="email" name="email" type="email" value="<?= $ov('email') ?>"></div>
  <div class="field sm:col-span-2"><label class="label" for="address_line">Address</label><input class="input" id="address_line" name="address_line" value="<?= $ov('address_line') ?>"></div>
  <div class="field"><label class="label" for="city">City</label><input class="input" id="city" name="city" value="<?= $ov('city') ?>"></div>
  <div class="field"><label class="label" for="state">State</label><input class="input" id="state" name="state" value="<?= $ov('state') ?>"></div>
  <div class="field"><label class="label" for="postal_code">PIN code</label><input class="input" id="postal_code" name="postal_code" value="<?= $ov('postal_code') ?>"></div>
  <div class="field"><label class="label" for="country">Country</label><input class="input" id="country" name="country" value="<?= $ov('country', 'India') ?>"></div>
  <div class="field"><label class="label" for="owner_id">Owner</label><select class="select" id="owner_id" name="owner_id"><?php $cur = old('owner_id') ?? $c['owner_id'] ?? $me['id']; foreach ($users as $u): ?><option value="<?= $u['id'] ?>"<?= selected_if((string) $cur === (string) $u['id']) ?>><?= esc($u['name']) ?></option><?php endforeach ?></select></div>
  <div class="field sm:col-span-2"><label class="label" for="description">Description</label><textarea class="textarea" id="description" name="description"><?= $ov('description') ?></textarea></div>
</div>
<?= view('partials/tag_input', ['tags' => $tags, 'value' => $c['tags'] ?? []]) ?>
<?php if ($defs): ?><div class="grid gap-3 sm:grid-cols-2"><?= view('partials/custom_fields', ['defs' => $defs, 'values' => $c['custom_fields'] ?? []]) ?></div><?php endif ?>
