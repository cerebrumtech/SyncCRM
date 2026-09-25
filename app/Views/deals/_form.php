<?php /** $deal|null, $pipelines, $pipeline (selected), $users, $tags, $defs, $contact, $company, $me, $prefill */ $d = $deal ?? []; $ov = fn ($k, $df = '') => old_or($k, $d[$k] ?? $df); $prefill = $prefill ?? []; $selPipeline = old('pipeline_id') ?? $d['pipeline_id'] ?? $pipeline['id'] ?? ''; $selStage = old('stage_id') ?? $d['stage_id'] ?? ''; ?>
<div class="grid gap-3 sm:grid-cols-2">
  <div class="field sm:col-span-2"><label class="label" for="title">Deal title *</label><input class="input" id="title" name="title" value="<?= $ov('title') ?>" required autofocus placeholder="e.g. SyncLMS Professional — Shivneri"></div>
  <div class="field"><label class="label" for="pipeline_id">Pipeline</label><select class="select" id="pipeline_id" name="pipeline_id" data-pipeline-select><?php foreach ($pipelines as $p): ?><option value="<?= $p['id'] ?>"<?= selected_if((string) $selPipeline === (string) $p['id']) ?>><?= esc($p['name']) ?></option><?php endforeach ?></select></div>
  <div class="field"><label class="label" for="stage_id">Stage</label><select class="select" id="stage_id" name="stage_id" data-stage-for-pipeline>
    <?php foreach ($pipelines as $p): foreach ($p['stages'] as $s): ?><option value="<?= $s['id'] ?>" data-pipeline="<?= $p['id'] ?>"<?= selected_if((string) $selStage === (string) $s['id']) ?><?= (string) $selPipeline !== (string) $p['id'] ? ' hidden' : '' ?>><?= esc($s['name']) ?></option><?php endforeach; endforeach ?></select></div>
  <div class="field"><label class="label" for="amount">Amount (₹)<?= ! empty($d) && ! $d['amount_is_manual'] ? ' <span class="muted">— from line items</span>' : '' ?></label><input class="input" id="amount" name="amount" type="number" min="0" max="999999999999.99" step="0.01" value="<?= $ov('amount', '') ?>"<?= ! empty($d) && ! $d['amount_is_manual'] ? ' readonly' : '' ?>></div>
  <div class="field"><label class="label" for="expected_close_date">Expected close date</label><input class="input" id="expected_close_date" name="expected_close_date" type="date" value="<?= esc(old('expected_close_date') ?? date_input($d['expected_close_date'] ?? null), 'attr') ?>"></div>
  <div class="field"><label class="label">Contact</label><div data-picker="/api/search/contacts" data-name="contact_id" data-value="<?= esc(old('contact_id') ?? $d['contact_id'] ?? $prefill['contact_id'] ?? '', 'attr') ?>" data-label="<?= esc(old('contact_id') ? '' : (isset($contact) && $contact ? full_name($contact) : ($prefill['contact_label'] ?? '')), 'attr') ?>" data-placeholder="Search contacts…"></div></div>
  <div class="field"><label class="label">Company</label><div data-picker="/api/search/companies" data-name="company_id" data-value="<?= esc(old('company_id') ?? $d['company_id'] ?? $prefill['company_id'] ?? '', 'attr') ?>" data-label="<?= esc(old('company_id') ? '' : ($company['name'] ?? $prefill['company_label'] ?? ''), 'attr') ?>" data-placeholder="Search companies…"></div></div>
  <div class="field"><label class="label" for="owner_id">Owner</label><select class="select" id="owner_id" name="owner_id"><?php $cur = old('owner_id') ?? $d['owner_id'] ?? $me['id']; foreach ($users as $u): ?><option value="<?= $u['id'] ?>"<?= selected_if((string) $cur === (string) $u['id']) ?>><?= esc($u['name']) ?></option><?php endforeach ?></select></div>
</div>
<?php if (empty($deal) && ! empty($products)): $chosen = (array) (old('products') ?? []); ?>
<div class="field">
  <label class="label">Products * <span class="muted">— a deal is a product sale, so pick at least one</span></label>
  <div class="grid gap-2 sm:grid-cols-3" data-deal-products>
  <?php foreach ($products as $pr): ?>
    <label class="flex items-center gap-2 rounded border border-line px-3 py-2 text-[13px]">
      <input type="checkbox" name="products[]" value="<?= $pr['id'] ?>"<?= in_array((string) $pr['id'], array_map('strval', $chosen), true) ? ' checked' : '' ?>>
      <span><?= esc($pr['name']) ?><br><span class="muted"><?= format_inr($pr['price']) ?></span></span>
    </label>
  <?php endforeach ?>
  </div>
  <p class="mt-1 text-xs muted">Leave Amount blank and it is worked out from the products you pick.</p>
</div>
<?php endif ?>
<?= view('partials/tag_input', ['tags' => $tags, 'value' => $d['tags'] ?? []]) ?>
<?php if ($defs): ?><div class="grid gap-3 sm:grid-cols-2"><?= view('partials/custom_fields', ['defs' => $defs, 'values' => $d['custom_fields'] ?? []]) ?></div><?php endif ?>
