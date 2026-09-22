<?php

namespace App\Libraries;

use App\Exceptions\FormError;
use App\Models\CompanyModel;
use App\Models\ContactModel;
use App\Models\DealModel;

/** Things shared by contact, company and deal pages: parent lookup, timeline, uploads. */
class Records
{
    public const MAX_UPLOAD = 15 * 1024 * 1024;

    public static function parentColumn(string $entity): string
    {
        return match ($entity) { 'CONTACT' => 'contact_id', 'COMPANY' => 'company_id', 'DEAL' => 'deal_id', default => throw new FormError('Unknown record type.') };
    }

    public static function load(int $orgId, string $entity, int $id): array
    {
        $model = match ($entity) { 'CONTACT' => model(ContactModel::class), 'COMPANY' => model(CompanyModel::class), 'DEAL' => model(DealModel::class), default => throw new FormError('Unknown record type.') };
        return $model->findInOrg($orgId, $id) ?? throw new FormError('Record not found.');
    }

    public static function path(string $entity, int $id): string
    {
        return Lists::PATHS[$entity] . '/' . $id;
    }

    public static function label(string $entity, array $record): string
    {
        return match ($entity) { 'CONTACT' => full_name($record), 'COMPANY' => $record['name'], default => $record['title'] };
    }

    public static function notes(int $orgId, string $col, int $id): array
    {
        return db_connect()->table('notes n')->select('n.*, u.name AS author_name, u.color AS author_color')->join('users u', 'u.id = n.author_id')
            ->where('n.organization_id', $orgId)->where("n.$col", $id)->orderBy('n.created_at', 'DESC')->get()->getResultArray();
    }

    public static function attachments(int $orgId, string $col, int $id): array
    {
        return db_connect()->table('attachments a')->select('a.*, u.name AS uploader_name')->join('users u', 'u.id = a.uploaded_by_id')
            ->where('a.organization_id', $orgId)->where("a.$col", $id)->orderBy('a.created_at', 'DESC')->get()->getResultArray();
    }

    public static function shares(int $orgId, string $entity, int $id): array
    {
        return db_connect()->table('record_shares rs')->select('rs.*, u.name AS user_name, u.color AS user_color, t.name AS team_name')
            ->join('users u', 'u.id = rs.user_id', 'left')->join('teams t', 't.id = rs.team_id', 'left')
            ->where('rs.organization_id', $orgId)->where('rs.entity', $entity)->where('rs.entity_id', $id)->get()->getResultArray();
    }

    /** Activities linked to a record, newest due first, with assignee details. */
    public static function activities(int $orgId, string $col, int $id): array
    {
        return db_connect()->table('activities a')->select('a.*, u.name AS assignee_name, u.color AS assignee_color')->join('users u', 'u.id = a.assignee_id', 'left')
            ->where('a.organization_id', $orgId)->where("a.$col", $id)->orderBy('a.status', 'DESC')->orderBy('a.due_at', 'ASC')->limit(100)->get()->getResultArray();
    }

    /** One chronological feed of activities, notes, files and audit entries for a record. */
    public static function timeline(int $orgId, string $entity, int $id): array
    {
        $db = db_connect();
        $col = self::parentColumn($entity);
        $items = [];
        foreach (self::activities($orgId, $col, $id) as $a) {
            $items[] = ['kind' => 'activity', 'at' => $a['due_at'] ?? $a['created_at'], 'title' => activity_type_label($a['type']) . ': ' . $a['title'], 'detail' => $a['description'],
                'meta' => ($a['status'] === 'COMPLETED' ? 'Completed' : ($a['status'] === 'CANCELLED' ? 'Cancelled' : ($a['due_at'] ? 'Due ' . format_datetime($a['due_at']) : 'Open'))) . ($a['assignee_name'] ? ' · ' . $a['assignee_name'] : ''), 'href' => '/activities?focus=' . $a['id']];
        }
        foreach (self::notes($orgId, $col, $id) as $n) {
            $items[] = ['kind' => 'note', 'at' => $n['created_at'], 'title' => 'Note by ' . $n['author_name'], 'detail' => $n['body']];
        }
        foreach (self::attachments($orgId, $col, $id) as $f) {
            $items[] = ['kind' => 'file', 'at' => $f['created_at'], 'title' => 'File: ' . $f['filename'], 'meta' => 'Uploaded by ' . $f['uploader_name'], 'href' => '/files/' . $f['id']];
        }
        $logs = $db->table('audit_logs l')->select('l.*, u.name AS actor_name')->join('users u', 'u.id = l.actor_id', 'left')
            ->where('l.organization_id', $orgId)->where('l.entity', $entity)->where('l.entity_id', (string) $id)
            ->whereNotIn('l.action', ['add_note', 'upload_file', 'delete_file'])->orderBy('l.id', 'DESC')->limit(100)->get()->getResultArray();
        $human = ['create' => 'Created', 'update' => 'Updated', 'delete' => 'Deleted', 'merge' => 'Merged', 'stage_change' => 'Stage changed', 'won' => 'Marked won', 'lost' => 'Marked lost', 'reopen' => 'Reopened', 'handoff' => 'Handed off'];
        foreach ($logs as $l) {
            $before = $l['before_data'] ? json_decode($l['before_data'], true) : null;
            $after = $l['after_data'] ? json_decode($l['after_data'], true) : null;
            $items[] = ['kind' => 'audit', 'at' => $l['created_at'], 'title' => ($human[$l['action']] ?? str_replace('_', ' ', $l['action'])) . ' by ' . ($l['actor_name'] ?? 'System'), 'detail' => self::summarize($before, $after)];
        }
        usort($items, fn ($a, $b) => strcmp($b['at'], $a['at']));
        return $items;
    }

    private static function summarize(?array $before, ?array $after): ?string
    {
        if (! $before || ! $after) {
            return null;
        }
        $fmt = static function ($v) {
            if ($v === null || $v === '') {
                return '—';
            }
            if (is_array($v)) {
                return implode(', ', array_map('strval', array_filter($v, 'is_scalar'))) ?: '—';
            }
            if (is_bool($v)) {
                return $v ? 'yes' : 'no';
            }
            $s = (string) $v;
            return mb_strlen($s) > 30 ? mb_substr($s, 0, 30) . '…' : $s;
        };
        $out = [];
        foreach ($after as $k => $v) {
            if (array_key_exists($k, $before) && json_encode($before[$k]) !== json_encode($v) && ! in_array($k, ['updated_at', 'custom_fields', 'phone_normalized'], true)) {
                $out[] = str_replace('_', ' ', $k) . ': ' . $fmt($before[$k]) . ' → ' . $fmt($v);
            }
            if (count($out) >= 4) {
                break;
            }
        }
        return $out ? implode(' · ', $out) : null;
    }

    public static function uploadDir(): string
    {
        $dir = rtrim(config('App')->uploadDir ?: WRITEPATH . 'uploads', '/');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    public static function findDuplicateContacts(int $orgId, ?string $email, ?string $phone, ?string $whatsapp, ?int $excludeId = null): array
    {
        $phones = array_values(array_filter([normalize_phone($phone), normalize_phone($whatsapp)]));
        $email = $email ? strtolower($email) : null;
        if (! $email && ! $phones) {
            return [];
        }
        $b = model(ContactModel::class)->where('organization_id', $orgId);
        if ($excludeId) {
            $b->where('id !=', $excludeId);
        }
        $b->groupStart();
        if ($email) {
            $b->where('email', $email);
        }
        if ($phones) {
            $b->orWhereIn('phone_normalized', $phones);
        }
        $b->groupEnd();
        return array_map(fn ($r) => ['id' => $r['id'], 'name' => full_name($r), 'email' => $r['email'], 'phone' => $r['phone'], 'reason' => $email && $r['email'] === $email ? 'Same email' : 'Same phone number'], $b->findAll(5));
    }

    public static function findDuplicateCompanies(int $orgId, string $name, ?int $excludeId = null): array
    {
        $b = model(CompanyModel::class)->where('organization_id', $orgId)->where('LOWER(name)', mb_strtolower($name));
        if ($excludeId) {
            $b->where('id !=', $excludeId);
        }
        return array_map(fn ($r) => ['id' => $r['id'], 'name' => $r['name'], 'reason' => 'Same name'], $b->findAll(5));
    }
}
