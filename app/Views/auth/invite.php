<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>
<?php if (! empty($invalid)): ?>
  <h1 class="mb-1 text-lg font-semibold text-navy">Invitation not valid</h1>
  <p class="text-[13px] text-ink-500">This link has expired or was revoked. Ask your administrator for a new invitation.</p>
  <a class="btn btn-secondary mt-4" href="/login">Go to sign in</a>
<?php else: ?>
  <h1 class="mb-1 text-lg font-semibold text-navy">Join <?= esc($org['name']) ?></h1>
  <p class="mb-4 text-[13px] text-ink-500">You were invited as <?= esc(role_label($invite['role'])) ?> (<?= esc($invite['email']) ?>). Choose a password to finish.</p>
  <form method="post" action="/invite/<?= esc($token, 'attr') ?>">
    <?= csrf_field() ?>
    <div class="field"><label class="label" for="name">Your name</label><input class="input" id="name" name="name" value="<?= old_or('name') ?>" required autofocus></div>
    <div class="field"><label class="label" for="password">Password</label><input class="input" id="password" name="password" type="password" minlength="8" required></div>
    <button class="btn btn-primary btn-lg w-full" type="submit">Create my account</button>
  </form>
<?php endif ?>
<?= $this->endSection() ?>
