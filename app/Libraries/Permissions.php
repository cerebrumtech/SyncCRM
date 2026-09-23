<?php

namespace App\Libraries;

use App\Exceptions\Forbidden;
use App\Models\RecordShareModel;

class Permissions
{
    private const RANK = ['OWNER' => 3, 'ADMIN' => 2, 'MEMBER' => 1];

    public static function isAdmin(array $user): bool
    {
        return (self::RANK[$user['role']] ?? 0) >= self::RANK['ADMIN'];
    }

    public static function isOwner(array $user): bool
    {
        return $user['role'] === 'OWNER';
    }

    public static function canAssignRole(array $actor, string $target): bool
    {
        return $target === 'OWNER' ? self::isOwner($actor) : self::isAdmin($actor);
    }

    /** Admins edit everything; members edit unowned records, their own, or records shared with them (or their team) for editing. */
    public static function canEditRecord(array $user, string $entity, array $record): bool
    {
        if ((int) $record['organization_id'] !== (int) $user['organization_id']) {
            return false;
        }
        if (self::isAdmin($user)) {
            return true;
        }
        if (empty($record['owner_id']) || (int) $record['owner_id'] === (int) $user['id']) {
            return true;
        }
        $db = db_connect();
        $row = $db->table('record_shares rs')
            ->select('rs.id')
            ->where('rs.organization_id', $user['organization_id'])
            ->where('rs.entity', $entity)
            ->where('rs.entity_id', $record['id'])
            ->where('rs.can_edit', 1)
            ->groupStart()
                ->where('rs.user_id', $user['id'])
                ->orWhere("rs.team_id IN (SELECT team_id FROM team_members WHERE user_id = " . (int) $user['id'] . ")", null, false)
            ->groupEnd()
            ->get()->getRowArray();
        return $row !== null;
    }

    public static function canDeleteRecord(array $user, array $record): bool
    {
        if ((int) $record['organization_id'] !== (int) $user['organization_id']) {
            return false;
        }
        return self::isAdmin($user) || (int) ($record['owner_id'] ?? 0) === (int) $user['id'];
    }

    public static function assert(bool $condition, ?string $message = null): void
    {
        if (! $condition) {
            throw $message ? new Forbidden($message) : new Forbidden();
        }
    }
}
