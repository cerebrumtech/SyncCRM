<?php

namespace App\Libraries;

/**
 * Who may read which records.
 *
 * Permissions decides who may *edit* or *delete*. This decides who may *see*, which until
 * now nothing did: every user could read every record in the organisation.
 *
 * A user is either 'all' (the whole organisation) or 'own' (records they own, plus records
 * explicitly shared with them or a team they belong to). The owner of the organisation is
 * always 'all' - there has to be someone who can see everything, and locking that person
 * out is not recoverable from inside the application.
 *
 * Records with no owner are visible to everyone. They are usually imported rows that were
 * never assigned, and hiding them from all but admins would quietly lose them.
 */
class Visibility
{
    public const ALL = 'all';
    public const OWN = 'own';

    /** The setting actually in force for this user. */
    public static function effective(array $user): string
    {
        if (($user['role'] ?? '') === 'OWNER') {
            return self::ALL;
        }
        return ($user['visibility'] ?? self::ALL) === self::OWN ? self::OWN : self::ALL;
    }

    public static function seesEverything(array $user): bool
    {
        return self::effective($user) === self::ALL;
    }

    /**
     * Narrow a list query to what this user may read.
     *
     * $alias is the table alias used in the query ('c' for contacts, 'd' for deals...).
     * $entity is the record_shares entity name, or null when that entity cannot be shared.
     */
    public static function apply($builder, array $user, string $alias, ?string $entity = null, string $ownerColumn = 'owner_id'): void
    {
        if (self::seesEverything($user)) {
            return;
        }
        $db = db_connect();
        $me = (int) $user['id'];
        $col = $alias . '.' . $ownerColumn;
        $builder->groupStart()
            ->where($col, $me)
            ->orWhere($col . ' IS NULL', null, false);
        if ($entity !== null) {
            $builder->orWhere(
                $alias . '.id IN (SELECT rs.entity_id FROM record_shares rs WHERE rs.organization_id = '
                . (int) $user['organization_id'] . ' AND rs.entity = ' . $db->escape($entity)
                . ' AND (rs.user_id = ' . $me
                . ' OR rs.team_id IN (SELECT team_id FROM team_members WHERE user_id = ' . $me . ')))',
                null,
                false
            );
        }
        $builder->groupEnd();
    }

    /** Whether this user may open one record. Mirrors apply(), for detail routes. */
    public static function canView(array $user, array $record, ?string $entity = null, string $ownerColumn = 'owner_id'): bool
    {
        if ((int) ($record['organization_id'] ?? 0) !== (int) $user['organization_id']) {
            return false;
        }
        if (self::seesEverything($user)) {
            return true;
        }
        $owner = $record[$ownerColumn] ?? null;
        if ($owner === null || (int) $owner === (int) $user['id']) {
            return true;
        }
        if ($entity === null) {
            return false;
        }
        $db = db_connect();
        $row = $db->table('record_shares rs')->select('rs.id')
            ->where('rs.organization_id', $user['organization_id'])
            ->where('rs.entity', $entity)
            ->where('rs.entity_id', $record['id'])
            ->groupStart()
                ->where('rs.user_id', $user['id'])
                ->orWhere('rs.team_id IN (SELECT team_id FROM team_members WHERE user_id = ' . (int) $user['id'] . ')', null, false)
            ->groupEnd()
            ->get()->getRowArray();
        return $row !== null;
    }
}
