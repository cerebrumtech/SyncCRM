<?php

namespace App\Controllers\Settings;

use App\Controllers\BaseController;
use App\Libraries\Audit;
use App\Models\TeamMemberModel;
use App\Models\TeamModel;
use App\Models\UserModel;

class Teams extends BaseController
{
    public function index()
    {
        $teams = model(TeamModel::class)->where('organization_id', $this->orgId())->orderBy('name')->findAll();
        $members = [];
        if ($teams) {
            foreach (model(TeamMemberModel::class)->whereIn('team_id', array_column($teams, 'id'))->findAll() as $m) {
                $members[$m['team_id']][] = (int) $m['user_id'];
            }
        }
        $users = model(UserModel::class)->where('organization_id', $this->orgId())->where('is_active', 1)->orderBy('name')->findAll();
        return $this->render('settings/teams', ['title' => 'Teams', 'teams' => $teams, 'members' => $members, 'users' => $users]);
    }

    public function create()
    {
        return $this->attempt(function () {
            $name = $this->str('name', 120);
            if (! $name || mb_strlen($name) < 2) {
                $this->fail('Team name is too short');
            }
            $m = model(TeamModel::class);
            if ($m->where('organization_id', $this->orgId())->where('name', $name)->first()) {
                $this->fail('A team with that name already exists.');
            }
            $id = $m->insert(['organization_id' => $this->orgId(), 'name' => $name]);
            Audit::log($this->me, 'create', 'Team', $id, $name);
            return $this->ok('Team created.', '/settings/teams');
        }, 'team-dialog');
    }

    public function delete(int $id)
    {
        return $this->attempt(function () use ($id) {
            $team = model(TeamModel::class)->findInOrg($this->orgId(), $id) ?? $this->fail('Team not found.');
            model(TeamModel::class)->delete($id);
            Audit::log($this->me, 'delete', 'Team', $id, $team['name']);
            return $this->ok('Team deleted.', '/settings/teams');
        });
    }

    public function members(int $id)
    {
        return $this->attempt(function () use ($id) {
            $team = model(TeamModel::class)->findInOrg($this->orgId(), $id) ?? $this->fail('Team not found.');
            $ids = array_map('intval', (array) $this->request->getPost('user_ids'));
            $valid = $ids ? array_column(model(UserModel::class)->where('organization_id', $this->orgId())->whereIn('id', $ids)->findAll(), 'id') : [];
            $tm = model(TeamMemberModel::class);
            $tm->where('team_id', $id)->delete();
            foreach ($valid as $uid) {
                $tm->insert(['team_id' => $id, 'user_id' => $uid]);
            }
            Audit::log($this->me, 'update_members', 'Team', $id, $team['name'], null, ['userIds' => $valid]);
            return $this->ok('Team members updated.', '/settings/teams');
        });
    }
}
