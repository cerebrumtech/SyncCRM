<?php

namespace App\Models;

class RecordShareModel extends AppModel
{
    protected $table         = 'record_shares';
    protected $allowedFields = ['organization_id', 'entity', 'entity_id', 'user_id', 'team_id', 'can_edit'];
    protected $useTimestamps = false;
    protected array $casts = [
        'id' => 'int',
        'can_edit' => 'int-bool',
    ];
}
