<?php

namespace App\Libraries;

use App\Models\SavedViewModel;
use App\Models\UserModel;
use CodeIgniter\Database\BaseBuilder;

/** Shared list queries (used by pages and CSV export) and small lookups. */
class Lists
{
    public const PATHS = ['CONTACT' => '/contacts', 'COMPANY' => '/companies', 'DEAL' => '/deals'];

    public static function activeUsers(int $orgId): array
    {
        return model(UserModel::class)->select('id, name, color, email')->where('organization_id', $orgId)->where('is_active', 1)->orderBy('name')->findAll();
    }

    public static function userMap(int $orgId): array
    {
        return array_column(model(UserModel::class)->select('id, name, color, email, is_active')->where('organization_id', $orgId)->findAll(), null, 'id');
    }

    public static function savedViews(int $orgId, string $entity, int $meId): array
    {
        return model(SavedViewModel::class)->select('saved_views.*, users.name AS owner_name')->join('users', 'users.id = saved_views.owner_id')
            ->where('saved_views.organization_id', $orgId)->where('saved_views.entity', $entity)
            ->groupStart()->where('saved_views.owner_id', $meId)->orWhere('saved_views.is_shared', 1)->groupEnd()
            ->orderBy('saved_views.is_shared')->orderBy('saved_views.name')->findAll();
    }

    /** Current GET filters (without page) for saved-view matching. */
    public static function currentFilters(): array
    {
        $out = [];
        foreach (service('request')->getGet() as $k => $v) {
            if ($k !== 'page' && is_string($v) && $v !== '') {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    public static function contacts(int $orgId, array $p): BaseBuilder
    {
        $db = db_connect();
        $b = $db->table('contacts c')
            ->select('c.*, co.name AS company_name, u.name AS owner_name, u.color AS owner_color, (SELECT COUNT(*) FROM deals d WHERE d.contact_id = c.id) AS deal_count')
            ->join('companies co', 'co.id = c.company_id', 'left')
            ->join('users u', 'u.id = c.owner_id', 'left')
            ->where('c.organization_id', $orgId);
        if (! empty($p['owner'])) {
            $b->where('c.owner_id', (int) $p['owner']);
        }
        if (! empty($p['company'])) {
            $b->where('c.company_id', (int) $p['company']);
        }
        if (! empty($p['tag'])) {
            $b->where('JSON_CONTAINS(c.tags, ' . $db->escape(json_encode($p['tag'])) . ')', null, false);
        }
        if (! empty($p['q'])) {
            $q = trim($p['q']);
            $b->groupStart()->like('c.first_name', $q)->orLike('c.last_name', $q)->orLike('c.email', $q)->orLike('c.phone', $q)->orLike('co.name', $q)->groupEnd();
        }
        $sort = $p['sort'] ?? 'updated';
        $dir = ($p['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        $b->orderBy(match ($sort) { 'name' => 'c.first_name', 'company' => 'co.name', 'created' => 'c.created_at', default => 'c.updated_at' }, $dir);
        return $b;
    }

    public static function companies(int $orgId, array $p): BaseBuilder
    {
        $db = db_connect();
        $b = $db->table('companies co')
            ->select('co.*, u.name AS owner_name, u.color AS owner_color, (SELECT COUNT(*) FROM contacts c WHERE c.company_id = co.id) AS contact_count, (SELECT COUNT(*) FROM deals d WHERE d.company_id = co.id AND d.status = "OPEN") AS open_deal_count')
            ->join('users u', 'u.id = co.owner_id', 'left')
            ->where('co.organization_id', $orgId);
        if (! empty($p['owner'])) {
            $b->where('co.owner_id', (int) $p['owner']);
        }
        if (! empty($p['tag'])) {
            $b->where('JSON_CONTAINS(co.tags, ' . $db->escape(json_encode($p['tag'])) . ')', null, false);
        }
        if (! empty($p['industry'])) {
            $b->where('co.industry', $p['industry']);
        }
        if (! empty($p['q'])) {
            $q = trim($p['q']);
            $b->groupStart()->like('co.name', $q)->orLike('co.city', $q)->orLike('co.industry', $q)->orLike('co.email', $q)->orLike('co.phone', $q)->groupEnd();
        }
        $sort = $p['sort'] ?? 'updated';
        $dir = ($p['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        $b->orderBy(match ($sort) { 'name' => 'co.name', 'city' => 'co.city', 'created' => 'co.created_at', default => 'co.updated_at' }, $dir);
        return $b;
    }

    public static function deals(int $orgId, array $p): BaseBuilder
    {
        $db = db_connect();
        $b = $db->table('deals d')
            ->select('d.*, s.name AS stage_name, s.color AS stage_color, s.probability, p.name AS pipeline_name, c.first_name, c.last_name, co.name AS company_name, u.name AS owner_name, u.color AS owner_color')
            ->join('stages s', 's.id = d.stage_id')
            ->join('pipelines p', 'p.id = d.pipeline_id')
            ->join('contacts c', 'c.id = d.contact_id', 'left')
            ->join('companies co', 'co.id = d.company_id', 'left')
            ->join('users u', 'u.id = d.owner_id', 'left')
            ->where('d.organization_id', $orgId);
        if (! empty($p['pipeline'])) {
            $b->where('d.pipeline_id', (int) $p['pipeline']);
        }
        if (! empty($p['stage'])) {
            $b->where('d.stage_id', (int) $p['stage']);
        }
        if (! empty($p['status'])) {
            $b->where('d.status', strtoupper($p['status']));
        }
        if (! empty($p['owner'])) {
            $b->where('d.owner_id', (int) $p['owner']);
        }
        if (! empty($p['contact'])) {
            $b->where('d.contact_id', (int) $p['contact']);
        }
        if (! empty($p['company'])) {
            $b->where('d.company_id', (int) $p['company']);
        }
        if (! empty($p['tag'])) {
            $b->where('JSON_CONTAINS(d.tags, ' . $db->escape(json_encode($p['tag'])) . ')', null, false);
        }
        if (! empty($p['q'])) {
            $q = trim($p['q']);
            $b->groupStart()->like('d.title', $q)->orLike('co.name', $q)->orLike('c.first_name', $q)->orLike('c.last_name', $q)->groupEnd();
        }
        $sort = $p['sort'] ?? 'updated';
        $dir = ($p['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        $b->orderBy(match ($sort) { 'title' => 'd.title', 'amount' => 'd.amount', 'close' => 'd.expected_close_date', 'stage' => 's.position', 'created' => 'd.created_at', default => 'd.updated_at' }, $dir);
        return $b;
    }
}
