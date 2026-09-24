<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?= view('partials/page_header', ['title' => 'Settings', 'subtitle' => 'Users, roles and invitations', 'actions' => '<button class="btn btn-primary" data-open="invite-dialog">' . icon('plus') . 'Invite user</button>']) ?>
<?= view('settings/_nav') ?>
<?php $isOwner = $me['role'] === 'OWNER'; $active = array_values(array_filter($users, fn ($u) => $u['is_active'])); ?>
<div class="card overflow-x-auto">
<table class="table">
  <thead><tr><th>User</th><th>Role</th><th>Sees</th><th>Status</th><th>Last sign-in</th><th class="text-right">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($users as $u): $self = $u['id'] === $me['id']; ?>
    <tr data-testid="user-row" data-email="<?= esc($u['email'], 'attr') ?>">
      <td><div class="flex items-center gap-2"><?= avatar($u['name'], $u['color'], 28) ?><div><div class="font-medium"><?= esc($u['name']) ?><?= $self ? ' <span class="muted">(you)</span>' : '' ?></div><div class="text-xs muted"><?= esc($u['email']) ?></div></div></div></td>
      <td>
        <?php if ($u['is_active'] && ! $self && ($u['role'] !== 'OWNER' || $isOwner)): ?>
        <form method="post" action="/settings/users/<?= $u['id'] ?>/role" class="inline"><?= csrf_field() ?>
          <select name="role" class="select w-32" data-submit-on-change aria-label="Role for <?= esc($u['name'], 'attr') ?>">
            <?php foreach ($roles as $r): if ($r === 'OWNER' && ! $isOwner) continue; ?><option value="<?= $r ?>"<?= selected_if($u['role'] === $r) ?>><?= role_label($r) ?></option><?php endforeach ?>
          </select>
        </form>
        <?php else: ?><?= badge(role_label($u['role']), $u['role'] === 'OWNER' ? 'info' : 'neutral') ?><?php endif ?>
      </td>
      <td>
        <?php if ($u['is_active'] && $u['role'] !== 'OWNER'): ?>
        <form method="post" action="/settings/users/<?= $u['id'] ?>/visibility" class="inline"><?= csrf_field() ?>
          <select name="visibility" class="select w-40" data-submit-on-change aria-label="What <?= esc($u['name'], 'attr') ?> can see">
            <option value="all"<?= selected_if(($u['visibility'] ?? 'all') !== 'own') ?>>All records</option>
            <option value="own"<?= selected_if(($u['visibility'] ?? 'all') === 'own') ?>>Only their own</option>
          </select>
        </form>
        <?php else: ?><?= badge('All records', 'info') ?><?php endif ?>
      </td>
      <td><?= $u['is_active'] ? badge('Active', 'success') : badge('Deactivated', 'neutral') ?></td>
      <td class="muted"><?= $u['last_login_at'] ? relative_time($u['last_login_at']) : 'Never' ?></td>
      <td class="text-right">
        <?php if ($self): ?>
        <?php elseif ($u['is_active']): ?>
          <?php if ($u['role'] !== 'OWNER' || $isOwner): ?><button class="btn btn-secondary btn-sm" data-open="deactivate-<?= $u['id'] ?>">Deactivate</button><?php endif ?>
          <dialog id="deactivate-<?= $u['id'] ?>" class="modal">
            <form method="post" action="/settings/users/<?= $u['id'] ?>/deactivate"><?= csrf_field() ?>
              <h2 class="card-title mb-1">Deactivate <?= esc($u['name']) ?>?</h2>
              <p class="mb-3 text-[13px] muted">They will be signed out and can no longer sign in. Their open records can be handed to a colleague.</p>
              <div class="field"><label class="label" for="reassign-<?= $u['id'] ?>">Reassign their contacts, companies, open deals and tasks to</label>
                <select class="select" id="reassign-<?= $u['id'] ?>" name="reassign_to"><option value="">Leave unassigned</option><?php foreach ($active as $o): if ($o['id'] === $u['id']) continue; ?><option value="<?= $o['id'] ?>"><?= esc($o['name']) ?></option><?php endforeach ?></select></div>
              <div class="flex justify-end gap-2"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-danger" type="submit">Deactivate</button></div>
            </form>
          </dialog>
        <?php else: ?>
          <form method="post" action="/settings/users/<?= $u['id'] ?>/reactivate" class="inline"><?= csrf_field() ?><button class="btn btn-secondary btn-sm" type="submit">Reactivate</button></form>
        <?php endif ?>
      </td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>
</div>

<?php if ($invites): ?>
<h2 class="card-title mt-6 mb-2">Pending invitations</h2>
<div class="card overflow-x-auto"><table class="table">
  <thead><tr><th>Email</th><th>Role</th><th>Expires</th><th class="text-right">Link</th></tr></thead>
  <tbody><?php foreach ($invites as $i): $link = rtrim(base_url(), '/') . '/invite/' . $i['token']; ?>
    <tr data-testid="invite-row"><td><?= esc($i['email']) ?></td><td><?= badge(role_label($i['role'])) ?></td><td class="muted"><?= format_date($i['expires_at']) ?></td>
      <td class="text-right"><button type="button" class="btn btn-secondary btn-sm" data-copy="<?= esc($link, 'attr') ?>">Copy link</button>
        <form method="post" action="/settings/users/invites/<?= $i['id'] ?>/revoke" class="inline"><?= csrf_field() ?><button class="btn btn-ghost btn-sm">Revoke</button></form></td></tr>
  <?php endforeach ?></tbody></table></div>
<?php endif ?>

<dialog id="invite-dialog" class="modal">
  <form method="post" action="/settings/users/invite"><?= csrf_field() ?>
    <h2 class="card-title mb-3">Invite a user</h2>
    <div class="field"><label class="label" for="invite-email">Email</label><input class="input" id="invite-email" name="email" type="email" value="<?= old_or('email') ?>" required></div>
    <div class="field"><label class="label" for="invite-role">Role</label><select class="select" id="invite-role" name="role">
      <option value="MEMBER">Member — works on their own and shared records</option><option value="ADMIN">Admin — manages users, pipelines and settings</option><?php if ($isOwner): ?><option value="OWNER">Owner — full control</option><?php endif ?></select></div>
    <div class="flex justify-end gap-2"><button type="button" class="btn btn-secondary" data-close>Cancel</button><button class="btn btn-primary" type="submit">Create invitation</button></div>
  </form>
</dialog>
<?= $this->endSection() ?>
