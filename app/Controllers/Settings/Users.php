<?php

namespace App\Controllers\Settings;

use App\Controllers\BaseController;
use App\Libraries\Auth;
use App\Libraries\Audit;
use App\Libraries\Permissions;
use App\Models\ActivityModel;
use App\Models\CompanyModel;
use App\Models\ContactModel;
use App\Models\DealModel;
use App\Models\InviteModel;
use App\Models\UserModel;

class Users extends BaseController
{
    private const ROLES = ['OWNER', 'ADMIN', 'MEMBER'];

    public function index()
    {
        $users = model(UserModel::class)->where('organization_id', $this->orgId())->orderBy('is_active', 'DESC')->orderBy('name')->findAll();
        $invites = model(InviteModel::class)->where('organization_id', $this->orgId())->where('status', 'PENDING')->where('expires_at >', now_sql())->orderBy('created_at', 'DESC')->findAll();
        return $this->render('settings/users', ['title' => 'Users', 'users' => $users, 'invites' => $invites, 'roles' => self::ROLES]);
    }

    public function invite()
    {
        return $this->attempt(function () {
            $email = strtolower((string) $this->str('email', 190));
            $role = $this->str('role') ?? 'MEMBER';
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->fail('Enter a valid email');
            }
            if (! in_array($role, self::ROLES, true)) {
                $this->fail('Choose a role.');
            }
            Permissions::assert(Permissions::canAssignRole($this->me, $role), 'Only an owner can invite another owner.');
            if (model(UserModel::class)->where('email', $email)->first()) {
                $this->fail('A user with this email already exists.');
            }
            $invites = model(InviteModel::class);
            $invites->where('organization_id', $this->orgId())->where('email', $email)->where('status', 'PENDING')->set(['status' => 'REVOKED'])->update();
            $token = Auth::token(24);
            $id = $invites->insert(['organization_id' => $this->orgId(), 'email' => $email, 'role' => $role, 'token' => $token, 'status' => 'PENDING', 'invited_by_id' => $this->me['id'], 'expires_at' => date('Y-m-d H:i:s', time() + 7 * 86400)]);
            Audit::log($this->me, 'invite', 'User', $id, $email, null, ['email' => $email, 'role' => $role]);
            $link = rtrim(base_url(), '/') . '/invite/' . $token;
            return redirect()->to('/settings/users')->with('info', 'Invitation created for <b>' . esc($email) . '</b>. Share this link (valid 7 days): <code class="select-all" data-testid="invite-link">' . esc($link) . '</code>');
        }, 'invite-dialog');
    }

    public function revoke(int $id)
    {
        return $this->attempt(function () use ($id) {
            $inv = model(InviteModel::class)->findInOrg($this->orgId(), $id) ?? $this->fail('Invite not found.');
            model(InviteModel::class)->update($id, ['status' => 'REVOKED']);
            Audit::log($this->me, 'revoke_invite', 'User', $id, $inv['email']);
            return $this->ok('Invitation revoked.', '/settings/users');
        });
    }

    private function ensureAnotherOwner(int $exceptUserId): void
    {
        $others = model(UserModel::class)->where('organization_id', $this->orgId())->where('role', 'OWNER')->where('is_active', 1)->where('id !=', $exceptUserId)->countAllResults();
        if ($others === 0) {
            $this->fail('The workspace must keep at least one active owner.');
        }
    }

    public function role(int $id)
    {
        return $this->attempt(function () use ($id) {
            $role = $this->str('role');
            if (! in_array($role, self::ROLES, true)) {
                $this->fail('Choose a role.');
            }
            $target = model(UserModel::class)->findInOrg($this->orgId(), $id) ?? $this->fail('User not found.');
            Permissions::assert(Permissions::canAssignRole($this->me, $role), 'Only an owner can grant the owner role.');
            Permissions::assert($target['role'] !== 'OWNER' || Permissions::isOwner($this->me), "Only an owner can change another owner's role.");
            if ($target['id'] === $this->me['id'] && $role !== 'OWNER' && $this->me['role'] === 'OWNER') {
                $this->ensureAnotherOwner($this->me['id']);
            }
            model(UserModel::class)->update($id, ['role' => $role]);
            Audit::log($this->me, 'update_role', 'User', $id, $target['email'], ['role' => $target['role']], ['role' => $role]);
            return $this->ok('Role updated.', '/settings/users');
        });
    }

    public function deactivate(int $id)
    {
        return $this->attempt(function () use ($id) {
            if ($id === (int) $this->me['id']) {
                $this->fail("You can't deactivate your own account.");
            }
            $users = model(UserModel::class);
            $target = $users->findInOrg($this->orgId(), $id) ?? $this->fail('User not found.');
            Permissions::assert($target['role'] !== 'OWNER' || Permissions::isOwner($this->me), 'Only an owner can deactivate another owner.');
            if ($target['role'] === 'OWNER') {
                $this->ensureAnotherOwner($id);
            }
            $reassignTo = $this->intOrNull('reassign_to');
            if ($reassignTo !== null) {
                $u = $users->where('organization_id', $this->orgId())->where('is_active', 1)->find($reassignTo);
                if (! $u) {
                    $this->fail('Choose an active user to reassign records to.');
                }
            }
            $db = db_connect();
            $db->transStart();
            $where = ['organization_id' => $this->orgId(), 'owner_id' => $id];
            model(ContactModel::class)->where($where)->set(['owner_id' => $reassignTo])->update();
            model(CompanyModel::class)->where($where)->set(['owner_id' => $reassignTo])->update();
            model(DealModel::class)->where($where)->where('status', 'OPEN')->set(['owner_id' => $reassignTo])->update();
            model(ActivityModel::class)->where(['organization_id' => $this->orgId(), 'assignee_id' => $id, 'status' => 'OPEN'])->set(['assignee_id' => $reassignTo])->update();
            $users->update($id, ['is_active' => false]);
            $db->transComplete();
            Audit::log($this->me, 'deactivate', 'User', $id, $target['email'], null, ['reassignedTo' => $reassignTo]);
            return $this->ok($target['name'] . ' deactivated.', '/settings/users');
        });
    }

    public function reactivate(int $id)
    {
        return $this->attempt(function () use ($id) {
            $target = model(UserModel::class)->findInOrg($this->orgId(), $id) ?? $this->fail('User not found.');
            model(UserModel::class)->update($id, ['is_active' => true]);
            Audit::log($this->me, 'reactivate', 'User', $id, $target['email']);
            return $this->ok($target['name'] . ' reactivated.', '/settings/users');
        });
    }
}
