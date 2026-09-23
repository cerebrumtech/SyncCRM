<?php

namespace App\Controllers\Settings;

use App\Controllers\BaseController;
use App\Models\AuditLogModel;
use App\Models\UserModel;

class Audit extends BaseController
{
    public function index()
    {
        $q = $this->request->getGet();
        $m = model(AuditLogModel::class)->where('organization_id', $this->orgId());
        if (! empty($q['entity'])) {
            $m->where('entity', $q['entity']);
        }
        if (! empty($q['actor'])) {
            $m->where('actor_id', (int) $q['actor']);
        }
        if (! empty($q['search'])) {
            $m->groupStart()->like('entity_label', $q['search'])->orLike('action', $q['search'])->groupEnd();
        }
        $perPage = 50;
        $page = max(1, (int) ($q['page'] ?? 1));
        $total = (clone $m)->countAllResults(false);
        $rows = $m->orderBy('id', 'DESC')->findAll($perPage, ($page - 1) * $perPage);
        $users = model(UserModel::class)->where('organization_id', $this->orgId())->orderBy('name')->findAll();
        $userMap = array_column($users, null, 'id');
        $entities = array_column(db_connect()->table('audit_logs')->select('entity')->distinct()->where('organization_id', $this->orgId())->orderBy('entity')->get()->getResultArray(), 'entity');
        return $this->render('settings/audit', ['title' => 'Audit log', 'rows' => $rows, 'users' => $users, 'userMap' => $userMap, 'entities' => $entities, 'q' => $q, 'total' => $total, 'page' => $page, 'perPage' => $perPage]);
    }
}
