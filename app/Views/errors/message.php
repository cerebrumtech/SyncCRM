<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($title) ?> · SyncCRM</title>
<link rel="icon" href="<?= base_url('brand/favicon-32x32.png') ?>">
<link rel="stylesheet" href="<?= base_url('assets/app.css?v=' . ASSET_VERSION) ?>">
</head>
<body class="min-h-screen bg-page">
  <div class="flex min-h-screen items-center justify-center p-4">
    <div class="card card-pad w-full max-w-md text-center">
      <h1 class="mb-1 text-lg font-semibold text-navy"><?= esc($title) ?></h1>
      <p class="mb-4 text-[13px] muted"><?= esc($message) ?></p>
      <a class="btn btn-primary" href="/">Back to SyncCRM</a>
    </div>
  </div>
</body>
</html>
