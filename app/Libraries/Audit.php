<?php

namespace App\Libraries;

use App\Models\AuditLogModel;

class Audit
{
    private const HIDDEN = ['password_hash', 'token'];

    public static function log(array $actor, string $action, string $entity, $entityId, ?string $label = null, ?array $before = null, ?array $after = null): void
    {
        model(AuditLogModel::class)->insert([
            'organization_id' => $actor['organization_id'],
            'actor_id'        => $actor['id'],
            'action'          => $action,
            'entity'          => $entity,
            'entity_id'       => (string) $entityId,
            'entity_label'    => $label !== null ? mb_substr($label, 0, 255) : null,
            'before_data'     => $before !== null ? self::clean($before) : null,
            'after_data'      => $after !== null ? self::clean($after) : null,
        ]);
    }

    private static function clean(array $data): array
    {
        foreach (self::HIDDEN as $k) {
            unset($data[$k]);
        }
        return $data;
    }
}
