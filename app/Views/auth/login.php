<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>
<h1 class="mb-1 text-lg font-semibold text-navy">Sign in</h1>
<p class="mb-4 text-[13px] text-ink-500">Use your SyncCRM account.</p>
<form method="post" action="/login">
  <?= csrf_field() ?>
  <input type="hidden" name="next" value="<?= esc($next ?? '', 'attr') ?>">
  <div class="field"><label class="label" for="email">Email</label><input class="input" id="email" name="email" type="email" value="<?= old_or('email') ?>" required autofocus></div>
  <div class="field"><label class="label" for="password">Password</label><input class="input" id="password" name="password" type="password" required></div>
  <button class="btn btn-primary btn-lg w-full" type="submit">Sign in</button>
</form>
<?= $this->endSection() ?>
