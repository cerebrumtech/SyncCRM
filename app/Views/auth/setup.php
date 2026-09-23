<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>
<h1 class="mb-1 text-lg font-semibold text-navy">Set up your workspace</h1>
<p class="mb-4 text-[13px] text-ink-500">Create the organisation and the first owner account. You can invite your team afterwards.</p>
<form method="post" action="/setup">
  <?= csrf_field() ?>
  <div class="field"><label class="label" for="orgName">Organisation name</label><input class="input" id="orgName" name="orgName" value="<?= old_or('orgName', 'SyncWorks Technologies Pvt. Ltd.') ?>" required autofocus></div>
  <div class="field"><label class="label" for="name">Your name</label><input class="input" id="name" name="name" value="<?= old_or('name') ?>" required></div>
  <div class="field"><label class="label" for="email">Email</label><input class="input" id="email" name="email" type="email" value="<?= old_or('email') ?>" required></div>
  <div class="field"><label class="label" for="password">Password</label><input class="input" id="password" name="password" type="password" minlength="8" required><p class="hint">At least 8 characters.</p></div>
  <button class="btn btn-primary btn-lg w-full" type="submit">Create workspace</button>
</form>
<?= $this->endSection() ?>
