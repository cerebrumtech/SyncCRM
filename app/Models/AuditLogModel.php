<?php

namespace App\Models;

class AuditLogModel extends AppModel
{
    protected $table         = 'audit_logs';
    protected $allowedFields = ['organization_id', 'actor_id', 'action', 'entity', 'entity_id', 'entity_label', 'before_data', 'after_data', 'created_at'];
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected array $casts = [
        'id' => 'int',
        'before_data' => '?json-array',
        'after_data' => '?json-array',
        'organization_id' => 'int',
        'actor_id' => '?int',
    ];
}
