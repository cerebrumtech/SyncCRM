<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($title ?? 'SyncCRM') ?> · SyncCRM</title>
<link rel="icon" href="<?= base_url('brand/favicon-32x32.png') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= base_url('assets/app.css?v=' . ASSET_VERSION) ?>">
</head>
<body class="min-h-screen bg-page">
<div class="flex min-h-screen items-center justify-center p-4">
  <div class="w-full max-w-md">
    <div class="mb-6 flex justify-center"><img src="<?= base_url('brand/logo-horizontal.svg') ?>" alt="SyncWorks" class="h-9"></div>
    <div class="card p-6">
      <?= view('partials/flash') ?>
      <?= $this->renderSection('content') ?>
    </div>
    <p class="mt-4 text-center text-xs text-ink-500">SyncCRM · SyncWorks Technologies Pvt. Ltd.</p>
  </div>
</div>
<script src="<?= base_url('assets/app.js?v=' . ASSET_VERSION) ?>"></script>
</body>
</html>
