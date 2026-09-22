<?php
/** @var array $me  current user */
/** @var array $org current organisation */
$nav = [
    ['href' => '/dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard'],
    ['href' => '/deals', 'label' => 'Deals', 'icon' => 'deals'],
    ['href' => '/contacts', 'label' => 'Contacts', 'icon' => 'contacts'],
    ['href' => '/companies', 'label' => 'Companies', 'icon' => 'companies'],
    ['href' => '/activities', 'label' => 'Activities', 'icon' => 'activities'],
    ['href' => '/products', 'label' => 'Products', 'icon' => 'products'],
];
if (\App\Libraries\Permissions::isAdmin($me)) {
    $nav[] = ['href' => '/settings', 'label' => 'Settings', 'icon' => 'settings'];
}
$path = '/' . ltrim(service('request')->getUri()->getPath(), '/');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($title ?? 'SyncCRM') ?> · SyncCRM</title>
<link rel="icon" href="<?= base_url('brand/favicon-32x32.png') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
<link rel="stylesheet" href="<?= base_url('assets/app.css?v=' . ASSET_VERSION) ?>">
<meta name="csrf-token" content="<?= csrf_hash() ?>">
<meta name="csrf-name" content="<?= csrf_token() ?>">
</head>
<body class="min-h-screen">
<div class="flex min-h-screen">
  <aside class="hidden w-56 shrink-0 flex-col bg-navy text-white md:flex" id="sidebar">
    <div class="flex h-14 items-center gap-2 px-4">
      <img src="<?= base_url('brand/icon.png') ?>" alt="" class="h-7 w-7 rounded">
      <span class="text-[15px] font-semibold tracking-tight">SyncCRM</span>
    </div>
    <nav class="flex-1 space-y-0.5 px-2 py-2">
      <?php foreach ($nav as $item): $active = $path === $item['href'] || str_starts_with($path, $item['href'] . '/'); ?>
        <a href="<?= $item['href'] ?>" class="nav-link<?= $active ? ' active' : '' ?>"><?= icon($item['icon']) ?><?= esc($item['label']) ?></a>
      <?php endforeach ?>
    </nav>
    <div class="border-t border-white/10 p-3">
      <div class="flex items-center gap-2">
        <?= avatar($me['name'], $me['color'], 32) ?>
        <div class="min-w-0 flex-1">
          <p class="truncate text-[13px] font-medium"><?= esc($me['name']) ?></p>
          <p class="truncate text-[11px] text-white/60"><?= esc(role_label($me['role'])) ?> · <?= esc($org['name']) ?></p>
        </div>
        <form method="post" action="/logout"><?= csrf_field() ?><button class="rounded p-1 text-white/70 hover:bg-white/10 hover:text-white" title="Sign out"><?= icon('logout') ?></button></form>
      </div>
    </div>
  </aside>
  <div class="flex min-w-0 flex-1 flex-col">
    <header class="flex h-12 items-center gap-3 border-b border-line-100 bg-white px-4 md:hidden">
      <button class="btn btn-ghost btn-sm" data-toggle-sidebar><?= icon('menu') ?></button>
      <span class="font-semibold text-navy">SyncCRM</span>
    </header>
    <main class="mx-auto w-full max-w-[1800px] flex-1 px-4 py-5 md:px-6">
      <?= view('partials/flash') ?>
      <?= $this->renderSection('content') ?>
    </main>
  </div>
</div>
<script src="<?= base_url('assets/sortable.min.js') ?>"></script>
<script src="<?= base_url('assets/app.js?v=' . ASSET_VERSION) ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
