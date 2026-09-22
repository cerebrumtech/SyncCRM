<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>
<h1 class="mb-1 text-lg font-semibold text-navy">Install SyncCRM</h1>
<p class="mb-4 text-[13px] muted">Runs the database setup for this installation. Remove <code>app.installKey</code> from <code>.env</code> once you are done.</p>
<?php if (! empty($log)): ?><div class="alert <?= str_starts_with($log[0], 'Error') ? 'alert-error' : 'alert-success' ?>"><?php foreach ($log as $l): ?><div><?= esc($l) ?></div><?php endforeach ?></div><?php endif ?>
<ul class="mb-4 space-y-1 text-[13px]">
  <li><?= $status['db'] ? '✅ Database connection OK' : '❌ Database connection failed: ' . esc($status['error']) ?></li>
  <li><?= $status['writable'] ? '✅ writable/ folder is writable' : '❌ writable/ folder is not writable (chmod 775)' ?></li>
  <li><?= $status['migrated'] ? '✅ Tables installed' . ($status['orgs'] ? ' · ' . $status['orgs'] . ' organisation(s)' : ' · no organisation yet') : '⬜ Tables not installed yet' ?></li>
</ul>
<form method="post" action="/install"><?= csrf_field() ?><input type="hidden" name="key" value="<?= esc($key ?? '', 'attr') ?>">
  <label class="mb-3 flex items-center gap-2 text-[13px]"><input type="checkbox" name="seed" value="1"> Also load the demo workspace (sample users, companies, deals)</label>
  <button class="btn btn-primary btn-lg w-full" type="submit"<?= $status['db'] ? '' : ' disabled' ?>><?= $status['migrated'] ? 'Re-run setup' : 'Install database' ?></button>
</form>
<?php if ($status['migrated']): ?><p class="mt-4 text-center text-[13px]"><a class="link" href="/">Open SyncCRM</a></p><?php endif ?>
<?= $this->endSection() ?>
