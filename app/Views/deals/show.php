<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?php $cf = $deal['custom_fields'] ?? []; $sub = 0; $tax = 0; foreach ($items as $it) { $net = $it['quantity'] * $it['unit_price'] * (1 - $it['discount_percent'] / 100); $sub += $net; $tax += $net * $it['tax_rate'] / 100; } ?>
<div class="mb-3 text-xs muted"><a href="/deals?pipeline=<?= $deal['pipeline_id'] ?>" class="hover:text-primary">Deals</a> / <?= esc($pipeline['name'] ?? '') ?> / <?= esc($deal['title']) ?></div>
<div class="mb-5 flex flex-wrap items-start justify-between gap-3">
  <div><h1 class="flex flex-wrap items-center gap-2 text-xl font-semibold text-navy"><?= esc($deal['title']) ?> <?= deal_status_badge($deal['status']) ?></h1>
    <p class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[13px] muted"><span class="text-base font-semibold text-navy tabular"><?= format_inr($deal['amount']) ?></span><?php if ($company): ?><a class="hover:text-primary" href="/companies/<?= $company['id'] ?>"><?= esc($company['name']) ?></a><?php endif ?><?php if ($contact): ?><a class="hover:text-primary" href="/contacts/<?= $contact['id'] ?>"><?= esc(full_name($contact)) ?></a><?php endif ?><?= tag_badges($deal['tags'] ?? []) ?></p>
    <?php if ($deal['status'] === 'LOST'): ?><p class="mt-1 text-[13px] text-danger">Lost: <?= esc($deal['lost_reason'] ?? 'no reason recorded') ?> <?php if ($canEdit): ?><button type="button" class="link" data-open="lost-edit-dialog">change</button><?php endif ?></p><?php endif ?>
  </div>
  <div class="flex flex-wrap items-center gap-2">
    <?= view('partials/activity_quick', ['linked' => ['deal_id' => $deal['id'], 'deal_label' => $deal['title'], 'contact_id' => $deal['contact_id'], 'contact_label' => $contact ? full_name($contact) : '', 'company_id' => $deal['company_id'], 'company_label' => $company['name'] ?? '']]) ?>
    <?php if ($canEdit): ?><button class="btn btn-secondary" data-open="deal-dialog">Edit</button><?php endif ?>
    <div class="relative"><button class="btn btn-secondary" data-dropdown><?= icon('chevron-down') ?></button>
      <div class="dropdown" hidden>
        <?php if ($canEdit): ?><button type="button" data-open="handoff-dialog"><?= icon('arrow-right', 'inline h-3.5 w-3.5') ?> Hand off to another pipeline…</button><button type="button" data-open="share-dialog"><?= icon('share', 'inline h-3.5 w-3.5') ?> Share…</button><?php endif ?>
        <?php if ($canDelete): ?><form method="post" action="/deals/<?= $deal['id'] ?>/delete" data-confirm="Delete this deal?"><?= csrf_field() ?><button class="text-danger"><?= icon('trash', 'inline h-3.5 w-3.5') ?> Delete</button></form><?php endif ?>
      </div></div>
  </div>
</div>
<?php if ($canEdit && $pipeline): ?>
<div class="card mb-5 flex flex-wrap items-center gap-2 px-4 py-3" data-testid="stage-stepper">
  <span class="text-xs font-medium muted">Stage</span>
  <?php foreach ($pipeline['stages'] as $i => $s): $reached = $s['position'] <= $stage['position']; ?><span class="flex items-center gap-1 text-[12px] <?= $s['id'] === $stage['id'] ? 'font-semibold text-navy' : ($reached ? 'text-ink-700' : 'muted') ?>"><span class="h-2.5 w-2.5 rounded-full" style="background:<?= $reached ? esc($s['color'], 'attr') : '#d1d1d1' ?>"></span><?= esc($s['name']) ?><?= $i < count($pipeline['stages']) - 1 ? '<span class="mx-1 muted">›</span>' : '' ?></span><?php endforeach ?>
  <select class="select ml-auto w-44" data-stage-select data-deal="<?= $deal['id'] ?>" aria-label="Move to stage"><?php foreach ($pipeline['stages'] as $s): ?><option value="<?= $s['id'] ?>"<?= selected_if($s['id'] === $stage['id']) ?>><?= esc($s['name']) ?> (<?= $s['probability'] ?>%)</option><?php endforeach ?></select>
</div>
<?php endif ?>
<div class="grid gap-5 lg:grid-cols-[340px_1fr]">
  <div class="space-y-5">
    <div class="card card-pad space-y-3 text-[13px]"><h2 class="card-title">Details</h2>
      <div class="grid grid-cols-[110px_1fr] gap-y-2">
        <span class="muted">Pipeline</span><span><?= esc($pipeline['name'] ?? '') ?> · <?= esc($stage['name']) ?> (<?= $stage['probability'] ?>%)</span>
        <span class="muted">Amount</span><span class="tabular"><?= format_inr($deal['amount'], true) ?> <span class="muted">(<?= $deal['amount_is_manual'] ? 'entered manually' : 'from line items' ?>)</span></span>
        <span class="muted">Expected close</span><span><?= format_date($deal['expected_close_date']) ?></span>
        <?php if ($deal['closed_at']): ?><span class="muted">Closed</span><span><?= format_datetime($deal['closed_at']) ?></span><?php endif ?>
        <span class="muted">Owner</span><span><?= $owner ? '<span class="inline-flex items-center gap-1.5">' . avatar($owner['name'], $owner['color'], 20) . esc($owner['name']) . '</span>' : 'Unassigned' ?></span>
        <span class="muted">Created</span><span><?= format_date($deal['created_at']) ?></span>
        <?php foreach ($defs as $d): ?><span class="muted"><?= esc($d['label']) ?></span><span><?= esc(\App\Libraries\CustomFields::display($d, $cf[$d['field_key']] ?? null)) ?></span><?php endforeach ?>
        <?php if ($source): ?><span class="muted">Handed off from</span><span><a class="link" href="/deals/<?= $source['id'] ?>"><?= esc($source['title']) ?></a></span><?php endif ?>
        <?php foreach ($handoffs as $h): ?><span class="muted">Handed off to</span><span><a class="link" href="/deals/<?= $h['id'] ?>"><?= esc($h['pipeline_name']) ?></a> · <?= esc($h['stage_name']) ?></span><?php endforeach ?>
      </div>
      <?php if ($shares): ?><div class="border-t border-line-100 pt-2 text-xs muted">Shared with <?= count($shares) ?></div><?php endif ?>
    </div>
  </div>
  <?php
  ob_start(); ?>
  <div id="items">
    <?php if ($canEdit): ?>
    <form method="post" action="/deals/<?= $deal['id'] ?>/line-items" data-line-items data-products="<?= esc(json_encode(array_map(fn ($p) => ['id' => $p['id'], 'name' => $p['name'], 'price' => $p['price'], 'tax_rate' => $p['tax_rate']], $products)), 'attr') ?>" data-items="<?= esc(json_encode(array_map(fn ($it) => ['product_id' => $it['product_id'], 'name' => $it['name'], 'quantity' => $it['quantity'] + 0, 'unit_price' => $it['unit_price'] + 0, 'discount_percent' => $it['discount_percent'] + 0, 'tax_rate' => $it['tax_rate'] + 0], $items)), 'attr') ?>"><?= csrf_field() ?>
      <template><tr data-row>
        <td><select class="select" name="items[0][product_id]"><option value="">Custom item</option><?php foreach ($products as $p): ?><option value="<?= $p['id'] ?>"><?= esc($p['name']) ?></option><?php endforeach ?></select></td>
        <td><input class="input" name="items[0][name]" placeholder="Description" required></td>
        <td><input class="input w-20 text-right" name="items[0][quantity]" type="number" step="0.01" min="0" value="1"></td>
        <td><input class="input w-28 text-right" name="items[0][unit_price]" type="number" step="0.01" min="0" value="0"></td>
        <td><input class="input w-20 text-right" name="items[0][discount_percent]" type="number" step="0.01" min="0" max="100" value="0"></td>
        <td><input class="input w-20 text-right" name="items[0][tax_rate]" type="number" step="0.01" min="0" max="100" value="18"></td>
        <td class="text-right tabular font-medium" data-total>₹0</td>
        <td><button type="button" class="muted hover:text-danger" data-remove title="Remove"><?= icon('x') ?></button></td>
      </tr></template>
      <div class="overflow-x-auto"><table class="table"><thead><tr><th class="w-44">Product</th><th>Name</th><th class="text-right">Qty</th><th class="text-right">Unit price</th><th class="text-right">Disc %</th><th class="text-right">GST %</th><th class="text-right">Total</th><th></th></tr></thead><tbody></tbody>
        <tfoot><tr><td colspan="6" class="text-right muted">Subtotal</td><td class="text-right tabular" data-subtotal>₹0</td><td></td></tr><tr><td colspan="6" class="text-right muted">GST</td><td class="text-right tabular" data-tax>₹0</td><td></td></tr><tr><td colspan="6" class="text-right font-semibold">Grand total</td><td class="text-right tabular font-semibold" data-grand>₹0</td><td></td></tr></tfoot></table></div>
      <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
        <button type="button" class="btn btn-secondary btn-sm" data-add-row><?= icon('plus') ?> Add line</button>
        <label class="flex items-center gap-2 text-[13px]"><input type="checkbox" name="amount_from_items"<?= checked_if(! $deal['amount_is_manual'] || ! $items) ?>> Set the deal amount from these items</label>
        <button class="btn btn-primary btn-sm" type="submit">Save line items</button>
      </div>
    </form>
    <?php else: ?>
      <?php if (! $items): ?><p class="muted text-[13px]">No line items.</p><?php else: ?><table class="table"><thead><tr><th>Item</th><th class="text-right">Qty</th><th class="text-right">Unit price</th><th class="text-right">Total</th></tr></thead><tbody><?php foreach ($items as $it): ?><tr><td><?= esc($it['name']) ?></td><td class="text-right"><?= $it['quantity'] + 0 ?></td><td class="text-right"><?= format_inr($it['unit_price']) ?></td><td class="text-right"><?= format_inr($it['total']) ?></td></tr><?php endforeach ?></tbody></table><?php endif ?>
    <?php endif ?>
  </div>
  <?php $itemsHtml = ob_get_clean(); ?>
  <?= view('partials/record_panels', ['entity' => 'DEAL', 'record' => $deal, 'extraTabs' => ['items' => ['Line items', count($items), $itemsHtml]]]) ?>
</div>
<?php if ($canEdit): ?>
<dialog id="deal-dialog" class="modal modal-lg"><form method="post" action="/deals/<?= $deal['id'] ?>" data-keep-enabled><?= csrf_field() ?>
  <h2 class="card-title mb-3">Edit deal</h2>
  <?= view('deals/_form', ['deal' => $deal, 'prefill' => []]) ?>
  <div class="mt-2 flex justify-end gap-2"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary" type="submit">Save changes</button></div>
</form></dialog>
<dialog id="handoff-dialog" class="modal"><form method="post" action="/deals/<?= $deal['id'] ?>/handoff"><?= csrf_field() ?>
  <h2 class="card-title mb-1">Hand off to another pipeline</h2>
  <p class="mb-3 text-[13px] muted">Creates a linked copy of this deal (with its line items) in the first stage of the chosen pipeline. This deal stays where it is.</p>
  <div class="field"><label class="label" for="handoff-pipeline">Pipeline</label><select class="select" id="handoff-pipeline" name="pipeline_id" required><?php foreach ($pipelines as $p): if ($p['id'] === $deal['pipeline_id']) continue; ?><option value="<?= $p['id'] ?>"><?= esc($p['name']) ?></option><?php endforeach ?></select></div>
  <div class="flex justify-end gap-2"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary" type="submit">Hand off</button></div>
</form></dialog>
<dialog id="lost-edit-dialog" class="modal"><form method="post" action="/deals/<?= $deal['id'] ?>/lost-reason"><?= csrf_field() ?>
  <h2 class="card-title mb-3">Lost reason</h2>
  <div class="field"><select class="select" name="lost_reason"><?php foreach ($lostReasons as $r): ?><option value="<?= esc($r, 'attr') ?>"<?= selected_if($deal['lost_reason'] === $r) ?>><?= esc($r) ?></option><?php endforeach ?></select></div>
  <div class="flex justify-end gap-2"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
</form></dialog>
<?= view('partials/share_dialog', ['entity' => 'DEAL', 'record' => $deal]) ?>
<?= view('deals/_lost_dialog') ?>
<?php endif ?>
<?= view('partials/activity_dialog', ['linked' => ['deal_id' => $deal['id'], 'deal_label' => $deal['title'], 'contact_id' => $deal['contact_id'], 'contact_label' => $contact ? full_name($contact) : '', 'company_id' => $deal['company_id'], 'company_label' => $company['name'] ?? '']]) ?>
<?= $this->endSection() ?>
