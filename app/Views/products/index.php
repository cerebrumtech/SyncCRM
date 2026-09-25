<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Products', 'subtitle' => 'Catalogue used for deal line items. Prices exclude GST.', 'actions' => $isAdmin ? '<button class="btn btn-primary" data-open="product-dialog" data-action="/products" data-fill=\'{"_title":"New product","name":"","sku":"","price":"","tax_rate":"18","description":"","is_active":true}\'>' . icon('plus') . 'New product</button>' : '']) ?>
<form class="mb-4 flex flex-wrap gap-2" method="get">
  <input class="input w-64" name="q" placeholder="Search name or SKU" value="<?= esc($p['q'] ?? '', 'attr') ?>">
  <select class="select w-36" name="status"><?php foreach (['active' => 'Active', 'inactive' => 'Inactive', 'all' => 'All'] as $k => $l): ?><option value="<?= $k ?>"<?= selected_if(($p['status'] ?? 'active') === $k) ?>><?= $l ?></option><?php endforeach ?></select>
  <button class="btn btn-secondary" type="submit">Apply</button>
  <a class="btn btn-secondary ml-auto" href="/export/products"><?= icon('download') ?> Export CSV</a>
</form>
<?php if (! $rows): ?><div class="empty">No products yet.</div><?php else: ?>
<div class="card overflow-x-auto"><table class="table" data-testid="products-table"><thead><tr><th>Product</th><th>SKU</th><th class="text-right">Price</th><th class="text-right">GST</th><th class="text-right">Incl. GST</th><th>Status</th><th class="text-right">On deals</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
  <tr data-testid="product-row"><td><div class="font-medium"><?= esc($r['name']) ?></div><?php if ($r['description']): ?><div class="text-xs muted"><?= esc($r['description']) ?></div><?php endif ?></td>
    <td class="tabular text-xs"><?= esc($r['sku'] ?? '—') ?></td><td class="text-right tabular"><?= format_inr($r['price'], true) ?></td><td class="text-right tabular"><?= $r['tax_rate'] + 0 ?>%</td><td class="text-right tabular font-medium"><?= format_inr($r['price'] * (1 + $r['tax_rate'] / 100)) ?></td>
    <td><?= $r['is_active'] ? badge('Active', 'success') : badge('Inactive') ?></td><td class="text-right tabular"><?= $usage[$r['id']] ?? 0 ?></td>
    <td class="whitespace-nowrap text-right"><?php if ($isAdmin): ?>
      <button type="button" class="btn btn-ghost btn-sm" data-open="product-dialog" data-action="/products/<?= $r['id'] ?>" data-fill="<?= esc(json_encode(['_title' => 'Edit product', 'name' => $r['name'], 'sku' => $r['sku'], 'price' => $r['price'] + 0, 'tax_rate' => $r['tax_rate'] + 0, 'description' => $r['description'], 'is_active' => (bool) $r['is_active']]), 'attr') ?>">Edit</button>
      <form method="post" action="/products/<?= $r['id'] ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="toggle_active" value="1"><button class="btn btn-ghost btn-sm"><?= $r['is_active'] ? 'Mark inactive' : 'Reactivate' ?></button></form>
      <form method="post" action="/products/<?= $r['id'] ?>/delete" class="inline" data-confirm="Delete <?= esc($r['name'], 'attr') ?>?"><?= csrf_field() ?><button class="btn btn-ghost btn-sm text-danger"><?= icon('trash', 'h-3.5 w-3.5') ?></button></form><?php endif ?></td></tr>
<?php endforeach ?>
</tbody></table></div><?php endif ?>
<?php if ($isAdmin): ?>
<dialog id="product-dialog" class="modal"><form method="post" action="/products"><?= csrf_field() ?>
  <h2 class="card-title mb-3" data-dialog-title>Product</h2>
  <div class="grid gap-3 sm:grid-cols-2">
    <div class="field sm:col-span-2"><label class="label" for="pname">Name *</label><input class="input" id="pname" name="name" value="<?= old_or('name') ?>" required autofocus></div>
    <div class="field"><label class="label" for="psku">SKU</label><input class="input" id="psku" name="sku" value="<?= old_or('sku') ?>"></div>
    <div class="field"><label class="label" for="pprice">Price (₹, excl. GST)</label><input class="input" id="pprice" name="price" type="number" max="999999999999.99" min="0" step="0.01" value="<?= old_or('price') ?>" required></div>
    <div class="field"><label class="label" for="ptax">GST %</label><input class="input" id="ptax" name="tax_rate" type="number" min="0" max="100" step="0.01" value="<?= old_or('tax_rate', '18') ?>"></div>
    <div class="field flex items-end"><label class="flex items-center gap-2 text-[13px]"><input type="checkbox" name="is_active" checked> Active</label></div>
    <div class="field sm:col-span-2"><label class="label" for="pdesc">Description</label><textarea class="textarea" id="pdesc" name="description"><?= old_or('description') ?></textarea></div>
  </div>
  <div class="flex justify-end gap-2"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary" type="submit">Save product</button></div>
</form></dialog>
<?php endif ?>
<?= $this->endSection() ?>
